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
 *
 * ## Kenapa $exception tidak ikut di-serialize
 *
 * Listener yang `implements ShouldQueue` (mis. AlertOnSyncFailure) membuat
 * Laravel men-serialize SELURUH objek event untuk dimasukkan ke queue. Objek
 * Throwable membawa stack trace, dan stack trace itu menyimpan argumen tiap
 * frame — termasuk closure bila ada `array_filter(..., fn (...))` di jalur yang
 * melempar. Closure tidak bisa di-serialize, jadi push ke queue gagal dengan
 * "Serialization of 'Closure' is not allowed" dan job masuk failed_jobs.
 *
 * Gejalanya menyesatkan: yang tercatat gagal adalah job sync-nya (mis.
 * SyncKeycloakClientsJob), padahal sync-nya sendiri sudah selesai — yang gagal
 * justru pengiriman notifikasi kegagalannya. Di produksi ini menumpuk jadi 222
 * failed_jobs sebelum ketemu.
 *
 * Karena itu $exception ditandai sebagai properti yang di-skip saat serialisasi
 * (lihat __sleep()), dan pesan + kelasnya disalin ke properti string biasa yang
 * aman dibawa lintas queue. Listener yang butuh detail sebaiknya memakai
 * $errorMessage / $exceptionClass, bukan $exception — properti itu null setelah
 * event melewati queue.
 */
class SyncJobFinalFailed
{
    use Dispatchable;
    use SerializesModels {
        __serialize as serializeViaTrait;
    }

    /**
     * Pesan error, aman untuk queue. Selalu terisi.
     */
    public readonly string $errorMessage;

    /**
     * Nama kelas exception, mis. "RuntimeException". Aman untuk queue.
     */
    public readonly string $exceptionClass;

    public function __construct(
        public readonly SyncJob $tracker,
        public ?Throwable $exception = null,
    ) {
        $this->errorMessage = $exception?->getMessage() ?? '';
        $this->exceptionClass = $exception ? $exception::class : '';
    }

    /**
     * Buang $exception dari payload serialisasi — lihat catatan kelas di atas.
     *
     * Harus meng-override __serialize(), bukan __sleep(): SerializesModels
     * mendefinisikan __serialize() sendiri, dan PHP mengabaikan __sleep() bila
     * __serialize() ada. Model Eloquent tetap dipadatkan jadi identifier oleh
     * trait, jadi kita panggil implementasinya lalu buang satu kunci.
     */
    public function __serialize(): array
    {
        // Lepas objek Throwable dulu supaya refleksi di trait tidak pernah
        // menyentuhnya, lalu kembalikan agar listener sinkron yang berjalan
        // setelah ini tetap menerima event yang utuh.
        $held = $this->exception;
        $this->exception = null;

        try {
            return $this->serializeViaTrait();
        } finally {
            $this->exception = $held;
        }
    }
}
