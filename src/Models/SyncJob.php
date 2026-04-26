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

    /**
     * Lazy-relation to the user who triggered the job. Uses the framework's
     * configured auth provider, so it works regardless of which package owns
     * the User model.
     */
    public function triggeredByUser()
    {
        $userModel = config('auth.providers.users.model');
        return $userModel
            ? $this->belongsTo($userModel, 'triggered_by')
            : null;
    }

    /**
     * Human-readable action label. Falls back to the raw action token when
     * not in the dictionary so unknown actions still render.
     */
    public function actionLabel(): string
    {
        $key = $this->service.'.'.$this->action;
        return self::ACTION_LABELS[$key] ?? self::ACTION_LABELS['*.'.$this->action] ?? $this->action;
    }

    /**
     * service.action => human label. Add new entries here as packages grow.
     * Use '*.action' for action names shared across services.
     */
    public const ACTION_LABELS = [
        // Generic
        '*.sync_users' => 'Sync Users',
        '*.sync_clients' => 'Sync Clients',
        '*.sync_zones' => 'Sync Zones',
        '*.sync_dns_records' => 'Sync DNS Records',
        '*.sync_emails' => 'Sync Email Accounts',
        '*.sync_accounts' => 'Sync cPanel Accounts',

        // WHM Email
        'whm.create_email' => 'Buat Email Account',
        'whm.update_email' => 'Update Email Account',
        'whm.delete_email' => 'Hapus Email Account',
        'whm.password_email' => 'Ganti Password Email',
        'whm.quota_email' => 'Ubah Quota Email',
        'whm.suspend_email' => 'Suspend Email',
        'whm.unsuspend_email' => 'Unsuspend Email',

        // WHM Account
        'whm.create_account' => 'Buat cPanel Account',
        'whm.update_account' => 'Update cPanel Account',
        'whm.terminate_account' => 'Terminate cPanel Account',
        'whm.suspend_account' => 'Suspend cPanel Account',
        'whm.unsuspend_account' => 'Unsuspend cPanel Account',
        'whm.password_account' => 'Ganti Password cPanel',
        'whm.package_account' => 'Ubah Package cPanel',

        // Cloudflare DNS
        'cloudflare.create_dns_record' => 'Buat DNS Record',
        'cloudflare.update_dns_record' => 'Update DNS Record',
        'cloudflare.delete_dns_record' => 'Hapus DNS Record',

        // Keycloak User
        'keycloak.toggle_user' => 'Enable/Disable User',
        'keycloak.reset_password' => 'Reset Password User',

        // Keycloak Client
        'keycloak.client_create' => 'Buat Client',
        'keycloak.client_update' => 'Update Client',
        'keycloak.client_delete' => 'Hapus Client',
    ];
}
