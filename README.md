# Nawasara Sync

The foundation package for the **DB-cache + queue pattern** used across every Nawasara service integration. Provides the sync-job tracker, repository contract, snapshot trait, audit log UI, and a system-health dashboard.

## The pattern

External services (Cloudflare, WHM, Keycloak, etc.) are slow, rate-limited, and unreliable. Hitting them on every page render is wrong. Instead, every Nawasara service package follows this shape:

```
External API  ◄─────  Mutation Job (queue)  ◄─────  User action
     │
     ▼  scheduled / on-demand sync
DB snapshot  ─────►  Repository  ─────►  Livewire page (paginated, fast)
```

- **Reads** come from a local snapshot table (paginated, indexed, fast)
- **Writes** dispatch queued mutation jobs that update the external API and the local snapshot atomically with content-hash conflict detection
- **Audit log** at `/admin/sync/jobs` records every dispatched job (action, target, user, payload with sensitive fields masked, status, duration, error)

## What this package ships

- **`SyncJob` model** — the audit row for every queued action across every package
- **`AbstractSyncJob`** — base for queued mutation/sync jobs. Auto-creates the tracker row in the constructor, marks it running/success/failed/conflict in `handle()`, and masks sensitive payload keys (password, secret, token, key) before persisting
- **`SyncedRepository`** contract — `list / find / all / create / update / delete / syncNow / lastSyncedAt`. Every service package implements this consistently
- **`HasSyncStatus` trait** — adds `sync_status`, `sync_error`, `last_synced_at`, and `content_hash` columns plus convenience scopes (`pendingSync`, `failed`, `synced`) and helpers (`markPending`, `markSynced`, `markFailed`)
- **`TracksLastSync` trait** — repository helper that resolves the last successful sync time from `nawasara_sync_jobs` instead of from per-record `last_synced_at` (which only updates when content changes)
- **`<x-nawasara-sync::sync-badge>`** — pill component that visualises a row's sync state in tables
- **System Health** at `/admin/sync/health` — totals, per-service breakdown with success rate, recent failures, and a window switcher
- **Sync Jobs** at `/admin/sync/jobs` — searchable, filterable audit log with retry and detail modal

## Installation

```bash
composer require nawasara/sync
php artisan migrate
php artisan db:seed --class="Nawasara\Sync\Database\Seeders\PermissionSeeder" --force
```

Auto-discovered.

## Implementing a service package

```php
use Nawasara\Sync\Concerns\HasSyncStatus;
use Nawasara\Sync\Concerns\TracksLastSync;
use Nawasara\Sync\Contracts\SyncedRepository;
use Nawasara\Sync\Jobs\AbstractSyncJob;

// 1. Snapshot model
class WhmEmailAccount extends Model
{
    use HasSyncStatus;

    public function computeContentHash(): string
    {
        return hash('sha256', json_encode([
            'email' => $this->email,
            'quota_mb' => $this->quota_mb,
            // … fields you want to detect drift on
        ]));
    }
}

// 2. Repository
class WhmEmailAccountRepository implements SyncedRepository
{
    use TracksLastSync;

    public function list(array $filters = [], int $perPage = 25) { /* ... */ }
    public function create(array $data): ?SyncJob { /* dispatch CreateWhmEmailJob */ }
    public function update($id, array $data): ?SyncJob { /* dispatch ChangeWhmEmail*Job */ }
    public function delete($id): ?SyncJob { /* dispatch DeleteWhmEmailJob */ }
    public function syncNow(): ?SyncJob { /* dispatch SyncWhmEmailsJob */ }

    public function lastSyncedAt(): ?Carbon
    {
        return $this->lastSuccessfulSyncAt('whm', 'sync_emails');
    }
}

// 3. Job
class ChangeWhmEmailQuotaJob extends AbstractSyncJob
{
    protected function service(): string { return 'whm'; }
    protected function action(): string { return 'quota_email'; }
    protected function targetType(): ?string { return 'WhmEmailAccount'; }
    protected function targetId(): ?string { return $this->payload['email']; }

    protected function execute(): array
    {
        // call external API…
        // update snapshot row…
        return ['quota_mb' => $newQuota];
    }
}
```

## Permissions

| Permission | Description |
|---|---|
| `sync.job.view` | View sync jobs audit log |
| `sync.job.manage` | Retry or delete sync job rows |

## Author

**Pringgo J. Saputro** &lt;odyinggo@gmail.com&gt;

## License

MIT
