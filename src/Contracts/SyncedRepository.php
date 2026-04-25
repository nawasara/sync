<?php

namespace Nawasara\Sync\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nawasara\Sync\Models\SyncJob;

/**
 * Standard contract untuk repositories yang manage data dari external system.
 *
 * Read selalu dari DB snapshot (cepat).
 * Write dispatch queue job (async).
 *
 * Concrete impl examples:
 *   - WhmEmailAccountRepository
 *   - CloudflareDnsRecordRepository
 *   - KeycloakUserRepository
 */
interface SyncedRepository
{
    /**
     * Paginated list dari DB snapshot.
     *
     * @param  array  $filters  service-specific (search, domain, status, etc.)
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * Find single record dari DB snapshot.
     */
    public function find(string|int $id): ?Model;

    /**
     * All records (no pagination) — gunakan untuk export/bulk ops.
     */
    public function all(array $filters = []): Collection;

    /**
     * Dispatch create job. Returns SyncJob tracker for UI feedback.
     */
    public function create(array $data): SyncJob;

    /**
     * Dispatch update job.
     */
    public function update(string|int $id, array $data): SyncJob;

    /**
     * Dispatch delete job.
     */
    public function delete(string|int $id): SyncJob;

    /**
     * Force-refresh dari external API ke DB.
     * Dispatch sync job, returns tracker.
     */
    public function syncNow(): SyncJob;

    /**
     * When was the last successful full sync?
     */
    public function lastSyncedAt(): ?\Carbon\Carbon;
}
