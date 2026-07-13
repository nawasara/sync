<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue per Service
    |--------------------------------------------------------------------------
    | Tiap service dapat queue dedicated supaya bisa di-priority-kan terpisah
    | di worker server (pakai --queue=default,whm-sync,cf-sync,kc-sync).
    */
    'queues' => [
        'default' => 'default',           // realtime user actions (priority tinggi)
        'whm_sync' => 'whm-sync',         // WHM scheduled sync
        'cloudflare_sync' => 'cf-sync',
        'keycloak_sync' => 'kc-sync',
        'secscan_sync' => 'secscan-scan', // secscan HTTP/WP scans (long-running, high timeout worker)
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Intervals (cron schedule)
    |--------------------------------------------------------------------------
    | Format: cron expression atau Laravel scheduler shortcut.
    | Configurable per service.
    */
    'intervals' => [
        'whm_email_accounts' => '0 * * * *',         // every hour
        'whm_cpanel_accounts' => '*/30 * * * *',     // every 30 min
        'cloudflare_zones' => '0 */6 * * *',         // every 6 hours
        'cloudflare_dns_records' => '0 * * * *',     // every hour
        'keycloak_users' => '0 * * * *',
        'keycloak_clients' => '0 */6 * * *',
    ],

    /*
    |--------------------------------------------------------------------------
    | Conflict Resolution Strategy
    |--------------------------------------------------------------------------
    | Hash-based skip: sebelum job execute, cek apakah data eksternal sama
    | dengan saat job dispatched. Kalau berubah, skip + alert.
    |
    | severity:
    |   - 'skip_alert'        : skip job, log sebagai conflict, kirim notif
    |   - 'last_write_wins'   : execute saja, overwrite
    |
    | Default per action type — bisa di-override per job class.
    */
    'conflict_strategy' => [
        'create' => 'skip_alert',           // duplicate avoidance
        'update_password' => 'skip_alert',  // sensitif
        'update_quota' => 'last_write_wins', // tidak destructive
        'suspend' => 'skip_alert',
        'unsuspend' => 'skip_alert',
        'delete' => 'skip_alert',
        'update' => 'last_write_wins',      // generic update
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Tracking Retention
    |--------------------------------------------------------------------------
    | Hapus sync_jobs lama setelah N hari. Failed jobs disimpan lebih lama
    | untuk audit.
    */
    'retention' => [
        'success_days' => 14,
        'failed_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial Sync Strategy
    |--------------------------------------------------------------------------
    | chunked: pecah ke beberapa batch paralel
    | sequential: 1-by-1 (lambat, simple)
    */
    'initial_sync' => [
        'strategy' => 'chunked',
        'chunk_size' => 100,
        'max_parallel' => 5,
    ],
];
