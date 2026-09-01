<?php

namespace Nawasara\Sync\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nawasara\Sync\Models\SyncJob;

/**
 * Dipancarkan AbstractSyncJob setelah `execute()` selesai tanpa galat.
 *
 * ## Kenapa ini ada
 *
 * Selama ini nawasara/sync hanya memancarkan kegagalan. Akibatnya alert
 * kegagalan sync **tidak pernah punya jalan untuk selesai**: ia menyala, lalu
 * menyala selamanya, bahkan setelah sinkronisasinya pulih pada percobaan
 * berikutnya beberapa menit kemudian.
 *
 * Di produksi (diperiksa 2 September 2026) itu meninggalkan 5.894 state yang
 * masih `firing`, semuanya dari kegagalan yang sudah lama pulih — dan 29.208
 * surel. Yang paling merugikan bukan jumlahnya, melainkan bahwa lencana
 * "firing" berhenti berarti apa-apa: operator berhenti membacanya, dan
 * kegagalan yang benar-benar baru ikut tenggelam.
 *
 * Loose coupling dijaga seperti [SyncJobFinalFailed]: nawasara/sync tidak
 * bergantung pada paket alerting. Bila tidak ada yang mendengarkan, event ini
 * sekadar tidak dipakai.
 *
 * Tidak membawa Throwable, jadi tidak perlu penjagaan serialisasi seperti
 * saudaranya.
 */
class SyncJobSucceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SyncJob $tracker,
    ) {}
}
