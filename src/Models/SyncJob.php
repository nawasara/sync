<?php

namespace Nawasara\Sync\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks every sync operation across all services.
 *
 * Status lifecycle:
 *   queued → running → success
 *                   ↘ failed (retryable)
 *                   ↘ conflict (data changed externally, skipped)
 *                   ↘ skipped (no-op)
 */
class SyncJob extends Model
{
    protected $table = 'nawasara_sync_jobs';

    protected $fillable = [
        'service', 'instance', 'action',
        'target_type', 'target_id', 'expected_hash',
        'status', 'payload', 'result', 'error', 'attempts',
        'started_at', 'finished_at', 'duration_ms',
        'triggered_by', 'trigger_source',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'attempts' => 'integer',
        'duration_ms' => 'integer',
        'triggered_by' => 'integer',
    ];

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_SKIPPED = 'skipped';

    /** Scope: pending jobs (queued or running). */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_QUEUED, self::STATUS_RUNNING]);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_CONFLICT]);
    }

    public function scopeForService($query, string $service)
    {
        return $query->where('service', $service);
    }

    public function scopeForTarget($query, string $type, $id)
    {
        return $query->where('target_type', $type)->where('target_id', (string) $id);
    }

    /** Mark as running, set started_at. */
    public function markRunning(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markSuccess(?array $result = null): void
    {
        $finishedAt = now();
        $duration = $this->started_at
            ? (int) abs($finishedAt->diffInMilliseconds($this->started_at))
            : null;

        $this->update([
            'status' => self::STATUS_SUCCESS,
            'finished_at' => $finishedAt,
            'duration_ms' => $duration,
            'result' => $result,
            'error' => null,
        ]);
    }

    public function markFailed(string $error, ?array $result = null): void
    {
        $finishedAt = now();
        $duration = $this->started_at
            ? (int) abs($finishedAt->diffInMilliseconds($this->started_at))
            : null;

        $this->update([
            'status' => self::STATUS_FAILED,
            'finished_at' => $finishedAt,
            'duration_ms' => $duration,
            'error' => $error,
            'result' => $result,
        ]);
    }

    public function markConflict(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_CONFLICT,
            'finished_at' => now(),
            'error' => $reason,
        ]);
    }

    public function markSkipped(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'finished_at' => now(),
            'error' => $reason,
        ]);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING]);
    }

    public function isRetryable(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CONFLICT]);
    }
}
