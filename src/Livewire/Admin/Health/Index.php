<?php

namespace Nawasara\Sync\Livewire\Admin\Health;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Sync\Models\SyncJob;

class Index extends Component
{
    public int $windowHours = 24;

    /** Aggregated stats per service for the active window. */
    #[Computed]
    public function services(): array
    {
        $since = now()->subHours($this->windowHours);

        $rows = SyncJob::query()
            ->selectRaw('service, status, count(*) as c')
            ->where('created_at', '>=', $since)
            ->groupBy('service', 'status')
            ->get();

        $services = [];
        foreach ($rows as $row) {
            $svc = $row->service;
            $services[$svc] ??= [
                'service' => $svc,
                'success' => 0, 'failed' => 0, 'conflict' => 0,
                'skipped' => 0, 'queued' => 0, 'running' => 0,
                'total' => 0,
            ];
            $services[$svc][$row->status] = (int) $row->c;
            $services[$svc]['total'] += (int) $row->c;
        }

        // Add last sync timestamp + pending count (all-time, not window-bound)
        foreach (array_keys($services) as $svc) {
            $services[$svc]['last_sync'] = SyncJob::query()
                ->where('service', $svc)
                ->where('status', SyncJob::STATUS_SUCCESS)
                ->latest('finished_at')
                ->value('finished_at');

            $services[$svc]['pending'] = SyncJob::query()
                ->where('service', $svc)
                ->pending()
                ->count();

            $finished = $services[$svc]['success'] + $services[$svc]['failed'] + $services[$svc]['conflict'];
            $services[$svc]['success_rate'] = $finished > 0
                ? round(($services[$svc]['success'] / $finished) * 100, 1)
                : null;
        }

        ksort($services);
        return array_values($services);
    }

    #[Computed]
    public function totals(): array
    {
        $since = now()->subHours($this->windowHours);

        return [
            'total' => SyncJob::where('created_at', '>=', $since)->count(),
            'success' => SyncJob::where('created_at', '>=', $since)
                ->where('status', SyncJob::STATUS_SUCCESS)->count(),
            'failed' => SyncJob::where('created_at', '>=', $since)
                ->whereIn('status', [SyncJob::STATUS_FAILED, SyncJob::STATUS_CONFLICT])->count(),
            'pending' => SyncJob::pending()->count(),
        ];
    }

    #[Computed]
    public function recentFailures()
    {
        return SyncJob::query()
            ->failed()
            ->latest('finished_at')
            ->limit(10)
            ->get();
    }

    public function setWindow(int $hours): void
    {
        $this->windowHours = max(1, $hours);
    }

    public function render()
    {
        return view('nawasara-sync::livewire.pages.admin.health.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
