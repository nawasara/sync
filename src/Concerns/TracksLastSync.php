<?php

namespace Nawasara\Sync\Concerns;

use Carbon\Carbon;
use Nawasara\Sync\Models\SyncJob;

/**
 * Helper for repositories: resolve the last successful sync time from
 * the sync_jobs audit table instead of from per-record last_synced_at.
 *
 * Why not snapshot rows? sync_jobs records WHEN a sync action was run;
 * snapshot rows only update last_synced_at when their content actually
 * changes. So a stable zone with no DNS edits would freeze the badge at
 * "X hours ago" even though the sync is still running fresh every hour.
 */
trait TracksLastSync
{
    /**
     * Return the finished_at of the most recent successful sync job for
     * the given service and one or more action names, or null if none.
     *
     * @param  string|array  $actions  e.g. 'sync_dns_records' or ['sync_zones', 'sync_dns_records']
     */
    protected function lastSuccessfulSyncAt(string $service, string|array $actions): ?Carbon
    {
        $actions = (array) $actions;

        $when = SyncJob::query()
            ->where('service', $service)
            ->whereIn('action', $actions)
            ->where('status', SyncJob::STATUS_SUCCESS)
            ->latest('finished_at')
            ->value('finished_at');

        return $when ? Carbon::parse($when) : null;
    }
}
