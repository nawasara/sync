<?php

namespace Nawasara\Sync\Concerns;

/**
 * Apply ke snapshot model untuk track sync status terhadap external system.
 *
 * Required columns di table:
 *   - sync_status (string, default 'synced')
 *   - sync_error  (text, nullable)
 *   - last_synced_at (timestamp, nullable)
 *
 * Status values:
 *   synced              — DB matches external
 *   pending_create      — record dibuat di Nawasara, belum ke external
 *   pending_update      — ada perubahan, belum di-push
 *   pending_delete      — di-mark untuk delete, belum di-push
 *   failed              — last sync attempt error
 *   conflict            — external data changed during pending update
 */
trait HasSyncStatus
{
    public const SYNC_SYNCED = 'synced';
    public const SYNC_PENDING_CREATE = 'pending_create';
    public const SYNC_PENDING_UPDATE = 'pending_update';
    public const SYNC_PENDING_DELETE = 'pending_delete';
    public const SYNC_FAILED = 'failed';
    public const SYNC_CONFLICT = 'conflict';

    public function scopePendingSync($query)
    {
        return $query->whereIn('sync_status', [
            self::SYNC_PENDING_CREATE,
            self::SYNC_PENDING_UPDATE,
            self::SYNC_PENDING_DELETE,
        ]);
    }

    public function scopeSynced($query)
    {
        return $query->where('sync_status', self::SYNC_SYNCED);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('sync_status', [self::SYNC_FAILED, self::SYNC_CONFLICT]);
    }

    public function markPending(string $type): static
    {
        $valid = [self::SYNC_PENDING_CREATE, self::SYNC_PENDING_UPDATE, self::SYNC_PENDING_DELETE];
        if (! in_array($type, $valid)) {
            throw new \InvalidArgumentException("Invalid pending type: {$type}");
        }

        $this->update([
            'sync_status' => $type,
            'sync_error' => null,
        ]);

        return $this;
    }

    public function markSynced(): static
    {
        $this->update([
            'sync_status' => self::SYNC_SYNCED,
            'sync_error' => null,
            'last_synced_at' => now(),
        ]);

        return $this;
    }

    public function markFailed(string $error): static
    {
        $this->update([
            'sync_status' => self::SYNC_FAILED,
            'sync_error' => $error,
        ]);

        return $this;
    }

    public function markConflict(string $error): static
    {
        $this->update([
            'sync_status' => self::SYNC_CONFLICT,
            'sync_error' => $error,
        ]);

        return $this;
    }

    public function isPendingSync(): bool
    {
        return in_array($this->sync_status, [
            self::SYNC_PENDING_CREATE,
            self::SYNC_PENDING_UPDATE,
            self::SYNC_PENDING_DELETE,
        ]);
    }

    public function isSynced(): bool
    {
        return $this->sync_status === self::SYNC_SYNCED;
    }

    public function hasFailedSync(): bool
    {
        return in_array($this->sync_status, [self::SYNC_FAILED, self::SYNC_CONFLICT]);
    }
}
