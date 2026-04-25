<?php

namespace Nawasara\Sync\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nawasara\Sync\Models\SyncJob;

/**
 * Base class for all sync jobs.
 *
 * Concrete subclasses must:
 *   - Define service(), action(), targetType(), targetId()
 *   - Implement execute() — actual work
 *   - Optionally override currentExternalHash() for conflict detection
 *
 * Lifecycle:
 *   1. Constructor: persist tracking row (status=queued)
 *   2. handle(): mark running → conflict check → execute → mark success/failed
 */
abstract class AbstractSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Backoff per attempt (seconds). */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public int $timeout = 300;

    /** ID of the SyncJob tracker row created when this job was dispatched. */
    public int $trackerId;

    public function __construct(
        public ?string $instance = null,
        public array $payload = [],
        public ?int $triggeredBy = null,
        public string $triggerSource = 'manual',
        public ?string $expectedHash = null,
    ) {
        // Auto-resolve user if context exists.
        if ($this->triggeredBy === null && auth()->check()) {
            $this->triggeredBy = auth()->id();
        }

        // Persist tracker row immediately so UI can show "queued" before worker picks it up.
        $tracker = SyncJob::create([
            'service' => $this->service(),
            'instance' => $this->instance,
            'action' => $this->action(),
            'target_type' => $this->targetType(),
            'target_id' => $this->targetId(),
            'expected_hash' => $this->expectedHash,
            'status' => SyncJob::STATUS_QUEUED,
            'payload' => $this->payload,
            'triggered_by' => $this->triggeredBy,
            'trigger_source' => $this->triggerSource,
        ]);

        $this->trackerId = $tracker->id;

        // Set queue based on action type (realtime vs scheduled)
        $this->onQueue($this->resolveQueue());
    }

    public function handle(): void
    {
        $tracker = SyncJob::find($this->trackerId);
        if (! $tracker) {
            // Tracker hilang (manual delete?) — abort silently
            return;
        }

        $tracker->markRunning();

        try {
            // Conflict detection: bandingkan hash saat dispatch vs sekarang
            if ($this->expectedHash !== null && $this->shouldCheckConflict()) {
                $currentHash = $this->currentExternalHash();
                if ($currentHash !== null && $currentHash !== $this->expectedHash) {
                    $tracker->markConflict(
                        'External data changed since job dispatched. Expected: '.
                        substr($this->expectedHash, 0, 8).
                        ', Got: '.substr($currentHash, 0, 8)
                    );
                    $this->onConflict($tracker);
                    return;
                }
            }

            $result = $this->execute();
            $tracker->markSuccess(is_array($result) ? $result : null);
            $this->onSuccess($tracker);
        } catch (\Throwable $e) {
            // Re-throw so Laravel queue can retry per $tries. Mark failed only on final attempt.
            $isLastAttempt = $this->attempts() >= $this->tries;
            if ($isLastAttempt) {
                $tracker->markFailed($e->getMessage());
                $this->onFinalFailure($tracker, $e);
            } else {
                // Reset to queued for next attempt
                $tracker->update([
                    'status' => SyncJob::STATUS_QUEUED,
                    'error' => 'Attempt '.$this->attempts().' failed: '.$e->getMessage(),
                ]);
            }
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        // Final failure handler called by Laravel after all retries exhausted.
        $tracker = SyncJob::find($this->trackerId);
        if ($tracker && $tracker->status !== SyncJob::STATUS_FAILED) {
            $tracker->markFailed('Job failed after '.$this->tries.' attempts: '.$e->getMessage());
        }
    }

    /**
     * Subclasses MUST implement: actual work to do.
     * Return array (saved as result) or null/void.
     */
    abstract protected function execute(): array|null;

    /** Service identifier: 'whm', 'cloudflare', 'keycloak', etc. */
    abstract protected function service(): string;

    /** Action type: 'sync', 'create', 'update_password', etc. */
    abstract protected function action(): string;

    /** Eloquent model basename or 'Service.Resource' for tracking. */
    abstract protected function targetType(): ?string;

    /** ID of the target (e.g. email address, record id). */
    abstract protected function targetId(): ?string;

    /**
     * Override to compute current external state hash for conflict detection.
     * Return null to skip conflict check.
     */
    protected function currentExternalHash(): ?string
    {
        return null;
    }

    /**
     * Whether to perform conflict check before execute.
     * Default: only when expectedHash was set at dispatch.
     * Override to make unconditional or skip entirely.
     */
    protected function shouldCheckConflict(): bool
    {
        return $this->expectedHash !== null;
    }

    /** Resolve queue name from config based on service. */
    protected function resolveQueue(): string
    {
        $service = $this->service();
        $key = $this->isScheduled() ? "{$service}_sync" : 'default';
        return config("nawasara-sync.queues.{$key}", 'default');
    }

    /** Whether this job is part of scheduled bulk sync (low priority). */
    protected function isScheduled(): bool
    {
        return $this->triggerSource === 'scheduled';
    }

    // Hooks — subclasses can override
    protected function onSuccess(SyncJob $tracker): void {}
    protected function onConflict(SyncJob $tracker): void {}
    protected function onFinalFailure(SyncJob $tracker, \Throwable $e): void {}
}
