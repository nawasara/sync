<?php

namespace Nawasara\Sync\Livewire\Admin\SyncJobs\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Sync\Models\SyncJob;
use Nawasara\Ui\Livewire\Concerns\HasBrowserToast;

class Table extends Component
{
    use HasBrowserToast, WithPagination;

    public string $serviceFilter = '';
    public string $statusFilter = '';
    public string $userFilter = '';
    public string $search = '';

    public ?int $detailId = null;

    protected function queryString(): array
    {
        return [
            'serviceFilter' => ['except' => '', 'as' => 'service'],
            'statusFilter' => ['except' => '', 'as' => 'status'],
            'userFilter' => ['except' => '', 'as' => 'user'],
            'search' => ['except' => ''],
        ];
    }

    public function updated($name): void
    {
        if (in_array($name, ['serviceFilter', 'statusFilter', 'userFilter', 'search'])) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function jobs()
    {
        return SyncJob::query()
            ->with('triggeredByUser:id,name,email')
            ->when($this->serviceFilter, fn ($q) => $q->where('service', $this->serviceFilter))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->userFilter !== '', function ($q) {
                if ($this->userFilter === 'system') {
                    $q->whereNull('triggered_by');
                } else {
                    $q->where('triggered_by', (int) $this->userFilter);
                }
            })
            ->when($this->search, function ($q) {
                $q->where(function ($qq) {
                    $qq->where('target_id', 'like', '%'.$this->search.'%')
                        ->orWhere('action', 'like', '%'.$this->search.'%')
                        ->orWhere('error', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(25);
    }

    #[Computed]
    public function userOptions(): array
    {
        // Distinct triggers seen in sync_jobs, plus the standard "system" bucket.
        $userModel = config('auth.providers.users.model');
        if (! $userModel) {
            return ['' => 'Semua User'];
        }

        $userIds = SyncJob::query()
            ->whereNotNull('triggered_by')
            ->distinct()
            ->pluck('triggered_by')
            ->all();

        $users = $userIds
            ? $userModel::query()->whereIn('id', $userIds)->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        $opts = ['' => 'Semua User', 'system' => 'System / Scheduler'];
        foreach ($users as $u) {
            $opts[(string) $u->id] = $u->name.' ('.$u->email.')';
        }
        return $opts;
    }

    #[Computed]
    public function services(): array
    {
        return SyncJob::query()
            ->select('service')
            ->distinct()
            ->orderBy('service')
            ->pluck('service', 'service')
            ->prepend('Semua Service', '')
            ->all();
    }

    #[Computed]
    public function statusCounts(): array
    {
        return SyncJob::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();
    }

    /**
     * Toggle behavior: klik card status yg sama → un-filter (kembali "all").
     * Klik beda → switch ke status itu.
     */
    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->dispatch('modal-open:sync-job-detail');
    }

    public function closeDetail(): void
    {
        $this->dispatch('modal-close:sync-job-detail');
        $this->detailId = null;
    }

    #[Computed]
    public function detail(): ?SyncJob
    {
        return $this->detailId ? SyncJob::find($this->detailId) : null;
    }

    public function retry(int $id): void
    {
        Gate::authorize('sync.job.manage');

        $job = SyncJob::find($id);
        if (! $job || ! $job->isRetryable()) {
            $this->toastError('Job tidak bisa di-retry.');
            return;
        }

        // Re-queue: ubah status ke queued, attempts reset, error null
        $job->update([
            'status' => SyncJob::STATUS_QUEUED,
            'attempts' => 0,
            'error' => null,
            'started_at' => null,
            'finished_at' => null,
            'duration_ms' => null,
        ]);

        // Note: actual re-dispatch handled by the calling service. UI hanya mark sebagai queued.
        // Untuk auto re-dispatch, perlu ada `job_class` field di tracker — out of scope MVP.
        $this->toastSuccess('Job di-mark untuk retry. Worker akan ambil saat polling berikutnya.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('sync.job.manage');

        SyncJob::where('id', $id)->delete();
        $this->toastSuccess('Job dihapus.');
    }

    public function render()
    {
        return view('nawasara-sync::livewire.pages.admin.sync-jobs.section.table');
    }
}
