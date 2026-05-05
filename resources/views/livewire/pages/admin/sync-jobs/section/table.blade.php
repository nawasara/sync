<div>
    {{-- Status summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
        @php
            $cards = [
                'queued' => ['label' => 'Queued', 'icon' => 'lucide-circle-dashed', 'color' => 'primary'],
                'running' => ['label' => 'Running', 'icon' => 'lucide-loader', 'color' => 'info'],
                'success' => ['label' => 'Success', 'icon' => 'lucide-check-circle', 'color' => 'success'],
                'failed' => ['label' => 'Failed', 'icon' => 'lucide-x-circle', 'color' => 'danger'],
                'conflict' => ['label' => 'Conflict', 'icon' => 'lucide-triangle-alert', 'color' => 'warning'],
                'skipped' => ['label' => 'Skipped', 'icon' => 'lucide-skip-forward', 'color' => 'neutral'],
            ];
        @endphp
        @foreach ($cards as $key => $cfg)
            <x-nawasara-ui::stat-card
                :label="$cfg['label']"
                :value="$this->statusCounts[$key] ?? 0"
                :icon="$cfg['icon']"
                :color="$cfg['color']"
                :active="$statusFilter === $key"
                wire:click="setStatusFilter('{{ $key }}')" />
        @endforeach
    </div>

    <x-nawasara-ui::filter-bar searchPlaceholder="Cari target, action, error..." searchModel="search">
        <x-nawasara-ui::filter-dropdown label="Service" model="serviceFilter" :items="$this->services" />
        <x-nawasara-ui::filter-dropdown label="User" model="userFilter" :items="$this->userOptions" />

        <x-slot:chips>
            @if ($statusFilter)
                <x-nawasara-ui::filter-chip label="Status: {{ ucfirst($statusFilter) }}" model="statusFilter" />
            @endif
            @if ($serviceFilter)
                <x-nawasara-ui::filter-chip label="Service: {{ $serviceFilter }}" model="serviceFilter" />
            @endif
            @if ($userFilter !== '')
                <x-nawasara-ui::filter-chip label="User: {{ $this->userOptions[$userFilter] ?? $userFilter }}" model="userFilter" />
            @endif
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table :headers="['Service', 'Action', 'Target', 'User', 'Status', 'Duration', 'Triggered', '']" title="Sync Jobs">
        <x-slot:table>
            @forelse ($this->jobs as $job)
                <tr>
                    <td class="px-6 py-3 whitespace-nowrap text-sm">
                        <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $job->service }}</span>
                        @if ($job->instance)
                            <span class="text-xs text-gray-500 dark:text-neutral-400">/ {{ $job->instance }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm">
                        <div class="text-gray-800 dark:text-neutral-200">{{ $job->actionLabel() }}</div>
                        <div class="text-xs text-gray-400 font-mono">{{ $job->action }}</div>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600 dark:text-neutral-400 max-w-xs truncate">
                        @if ($job->target_id)
                            <span class="font-mono">{{ $job->target_id }}</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm">
                        @if ($job->triggeredByUser)
                            <div class="text-gray-700 dark:text-neutral-300">{{ $job->triggeredByUser->name }}</div>
                            <div class="text-xs text-gray-400 truncate max-w-[12rem]">{{ $job->triggeredByUser->email }}</div>
                        @else
                            <span class="text-xs italic text-gray-400">system</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm">
                        @php
                            $statusClass = match ($job->status) {
                                'queued' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                'running' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 animate-pulse',
                                'success' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                'conflict' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                                'skipped' => 'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-400',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                            {{ ucfirst($job->status) }}
                        </span>
                        @if ($job->attempts > 1)
                            <span class="ml-1 text-xs text-gray-500" title="Attempts">×{{ $job->attempts }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-neutral-400 font-mono">
                        @if ($job->duration_ms !== null)
                            {{ $job->duration_ms < 1000 ? $job->duration_ms.'ms' : round($job->duration_ms / 1000, 1).'s' }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $job->created_at->diffForHumans() }}
                        <div class="text-xs text-gray-400">{{ $job->trigger_source }}</div>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$job->id" :items="array_filter([
                            ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail('.$job->id.')', 'modal' => 'sync-job-detail', 'icon' => 'lucide-eye', 'permission' => 'sync.job.view'],
                            $job->isRetryable() ? ['type' => 'click', 'label' => 'Retry', 'wire:click' => 'retry('.$job->id.')', 'icon' => 'lucide-refresh-cw', 'permission' => 'sync.job.manage'] : null,
                            ['type' => 'click', 'label' => 'Hapus', 'wire:click' => 'delete('.$job->id.')', 'icon' => 'lucide-trash-2', 'confirm' => 'Hapus log job ini?', 'permission' => 'sync.job.manage'],
                        ])" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Belum ada sync job.
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->jobs->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>

    {{-- Detail Modal --}}
    <x-nawasara-ui::modal id="sync-job-detail" maxWidth="2xl" :title="'Sync Job #' . ($detailId ?? '')">
        @if ($this->detail)
            @php $j = $this->detail; @endphp
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="col-span-2 p-3 rounded bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900">
                        <div class="text-xs text-blue-700 dark:text-blue-400 uppercase mb-1">Action</div>
                        <div class="font-semibold text-gray-800 dark:text-neutral-200">{{ $j->actionLabel() }}</div>
                        <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $j->service }}.{{ $j->action }}</div>
                    </div>
                    <div><span class="text-gray-500">Service:</span> <span class="font-medium">{{ $j->service }}</span></div>
                    <div><span class="text-gray-500">Instance:</span> <span class="font-medium">{{ $j->instance ?? '—' }}</span></div>
                    <div class="col-span-2"><span class="text-gray-500">User:</span>
                        @if ($j->triggeredByUser)
                            <span class="font-medium">{{ $j->triggeredByUser->name }}</span>
                            <span class="text-xs text-gray-500">({{ $j->triggeredByUser->email }})</span>
                        @else
                            <span class="italic text-gray-400">system / scheduler</span>
                        @endif
                    </div>
                    <div><span class="text-gray-500">Status:</span> <span class="font-medium">{{ ucfirst($j->status) }}</span></div>
                    <div><span class="text-gray-500">Target:</span> <span class="font-mono text-xs">{{ $j->target_type }}#{{ $j->target_id }}</span></div>
                    <div><span class="text-gray-500">Attempts:</span> <span>{{ $j->attempts }}</span></div>
                    <div><span class="text-gray-500">Duration:</span>
                        @if ($j->duration_ms !== null)
                            {{ $j->duration_ms < 1000 ? $j->duration_ms.'ms' : round($j->duration_ms / 1000, 1).'s' }}
                        @else
                            —
                        @endif
                    </div>
                    <div><span class="text-gray-500">Triggered:</span> {{ $j->trigger_source }}</div>
                    <div class="col-span-2"><span class="text-gray-500">Created:</span> {{ $j->created_at->format('Y-m-d H:i:s') }}</div>
                    @if ($j->started_at)
                        <div class="col-span-2"><span class="text-gray-500">Started:</span> {{ $j->started_at->format('Y-m-d H:i:s') }}</div>
                    @endif
                    @if ($j->finished_at)
                        <div class="col-span-2"><span class="text-gray-500">Finished:</span> {{ $j->finished_at->format('Y-m-d H:i:s') }}</div>
                    @endif
                </div>

                @if ($j->error)
                    <div>
                        <h4 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-1">Error</h4>
                        <pre class="text-xs bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 p-3 rounded overflow-x-auto whitespace-pre-wrap">{{ $j->error }}</pre>
                    </div>
                @endif

                @if (! empty($j->payload))
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-neutral-300 mb-1 flex items-center gap-2">
                            Payload
                            <span class="text-xs font-normal text-gray-400" title="Field sensitif (password, token, key) di-mask sebagai *** sebelum disimpan">
                                <x-lucide-shield class="size-3 inline" /> sensitive masked
                            </span>
                        </h4>
                        <pre class="text-xs bg-gray-50 dark:bg-neutral-900 p-3 rounded overflow-x-auto">{{ json_encode($j->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif

                @if (! empty($j->result))
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-neutral-300 mb-1">Result</h4>
                        <pre class="text-xs bg-gray-50 dark:bg-neutral-900 p-3 rounded overflow-x-auto">{{ json_encode($j->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif
            </div>
            <x-slot:footer>
                <x-nawasara-ui::button color="neutral" variant="outline" wire:click="closeDetail">Tutup</x-nawasara-ui::button>
            </x-slot:footer>
        @endif
    </x-nawasara-ui::modal>
</div>
