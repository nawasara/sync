<?php

namespace Nawasara\Sync\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nawasara\Sync\Models\SyncJob;
use Throwable;

/**
 * Fired by AbstractSyncJob::onFinalFailure() — setelah semua retry habis dan
 * job tetap gagal. Listener (e.g. notification package) bisa subscribe untuk
 * trigger alert ke admin.
 *
 * Loose coupling: nawasara/sync tidak depend ke nawasara/notification. Kalau
 * notification package tidak ke-install, event di-emit tapi tidak ada listener.
 */
class SyncJobFinalFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SyncJob $tracker,
        public readonly Throwable $exception,
    ) {
    }
}
