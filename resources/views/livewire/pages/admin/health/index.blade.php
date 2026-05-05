<div wire:poll.30s>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Pengaturan', 'url' => '#'], ['label' => 'System Health']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <div class="flex items-center justify-between mb-6">
            <div>
                <x-nawasara-ui::page.title>System Health</x-nawasara-ui::page.title>
                <p class="text-sm text-gray-500 dark:text-neutral-400">
                    Status sinkronisasi & queue kesehatan tiap service. Auto-refresh tiap 30 detik.
                </p>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span class="text-gray-500 dark:text-neutral-400">Window:</span>
                <x-nawasara-ui::segmented-control
                    :options="['1' => '1h', '24' => '24h', '168' => '7d']"
                    :active="(string) $windowHours"
                    wire-method="setWindow"
                    size="sm" />
            </div>
        </div>

        {{-- Top-level totals --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="p-5 rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800">
                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-neutral-400 uppercase">
                    <x-lucide-activity class="size-4" /> Total Jobs
                </div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($this->totals['total']) }}</div>
            </div>
            <div class="p-5 rounded-xl border border-green-200 dark:border-green-900 bg-green-50/50 dark:bg-green-900/10">
                <div class="flex items-center gap-2 text-xs text-green-700 dark:text-green-400 uppercase">
                    <x-lucide-check-circle class="size-4" /> Success
                </div>
                <div class="mt-2 text-3xl font-bold text-green-700 dark:text-green-400">{{ number_format($this->totals['success']) }}</div>
            </div>
            <div class="p-5 rounded-xl border {{ $this->totals['failed'] > 0 ? 'border-red-200 dark:border-red-900 bg-red-50/50 dark:bg-red-900/10' : 'border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800' }}">
                <div class="flex items-center gap-2 text-xs uppercase {{ $this->totals['failed'] > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-neutral-400' }}">
                    <x-lucide-x-circle class="size-4" /> Failed / Conflict
                </div>
                <div class="mt-2 text-3xl font-bold {{ $this->totals['failed'] > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($this->totals['failed']) }}</div>
            </div>
            <div class="p-5 rounded-xl border {{ $this->totals['pending'] > 0 ? 'border-blue-200 dark:border-blue-900 bg-blue-50/50 dark:bg-blue-900/10' : 'border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800' }}">
                <div class="flex items-center gap-2 text-xs uppercase {{ $this->totals['pending'] > 0 ? 'text-blue-700 dark:text-blue-400' : 'text-gray-500 dark:text-neutral-400' }}">
                    <x-lucide-clock class="size-4 {{ $this->totals['pending'] > 0 ? 'animate-pulse' : '' }}" /> Pending
                </div>
                <div class="mt-2 text-3xl font-bold {{ $this->totals['pending'] > 0 ? 'text-blue-700 dark:text-blue-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($this->totals['pending']) }}</div>
            </div>
        </div>

        {{-- Per-service cards --}}
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Per Service</h2>
        @if (empty($this->services))
            <div class="text-center py-12 border-2 border-dashed border-gray-200 dark:border-neutral-700 rounded-xl">
                <x-lucide-database class="size-12 mx-auto text-gray-300 dark:text-neutral-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-neutral-400">
                    Belum ada sync job dalam window {{ $windowHours }}h terakhir.
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                @foreach ($this->services as $svc)
                    <div class="p-5 rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-base font-bold text-gray-900 dark:text-white capitalize">{{ $svc['service'] }}</h3>
                            @if ($svc['success_rate'] !== null)
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                    {{ $svc['success_rate'] >= 95 ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : ($svc['success_rate'] >= 80 ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400') }}">
                                    {{ $svc['success_rate'] }}%
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center text-xs mb-3">
                            <div>
                                <div class="font-bold text-green-600 dark:text-green-400">{{ $svc['success'] }}</div>
                                <div class="text-gray-500 dark:text-neutral-400">Success</div>
                            </div>
                            <div>
                                <div class="font-bold {{ ($svc['failed'] + $svc['conflict']) > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">{{ $svc['failed'] + $svc['conflict'] }}</div>
                                <div class="text-gray-500 dark:text-neutral-400">Failed</div>
                            </div>
                            <div>
                                <div class="font-bold {{ $svc['pending'] > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400' }}">{{ $svc['pending'] }}</div>
                                <div class="text-gray-500 dark:text-neutral-400">Pending</div>
                            </div>
                        </div>

                        <div class="text-xs text-gray-500 dark:text-neutral-400 border-t border-gray-100 dark:border-neutral-700 pt-3">
                            <div class="flex items-center gap-1.5">
                                <x-lucide-clock class="size-3" />
                                Last sync:
                                @if ($svc['last_sync'])
                                    <span class="text-gray-700 dark:text-neutral-300 font-medium">{{ \Carbon\Carbon::parse($svc['last_sync'])->diffForHumans() }}</span>
                                @else
                                    <span class="text-yellow-600 dark:text-yellow-400">belum pernah</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Recent failures --}}
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Recent Failures</h2>
        @if ($this->recentFailures->isEmpty())
            <div class="p-6 rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-center">
                <x-lucide-check-circle class="size-8 mx-auto text-green-500" />
                <p class="mt-2 text-sm text-gray-600 dark:text-neutral-400">Tidak ada failure terbaru — semua bersih.</p>
            </div>
        @else
            <x-nawasara-ui::table :headers="['Service', 'Action', 'Target', 'Error', 'Kapan']" title="">
                <x-slot:table>
                    @foreach ($this->recentFailures as $job)
                        <tr>
                            <td class="px-6 py-3 text-sm text-gray-700 dark:text-neutral-300 capitalize">{{ $job->service }}</td>
                            <td class="px-6 py-3 text-sm text-gray-700 dark:text-neutral-300 font-mono">{{ $job->action }}</td>
                            <td class="px-6 py-3 text-sm text-gray-500 dark:text-neutral-400 font-mono truncate max-w-xs">{{ $job->target_id ?? '-' }}</td>
                            <td class="px-6 py-3 text-sm">
                                <span class="text-xs text-red-600 dark:text-red-400 font-mono truncate block max-w-md" title="{{ $job->error }}">{{ \Illuminate\Support\Str::limit($job->error, 80) }}</span>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-500 dark:text-neutral-400">{{ $job->finished_at?->diffForHumans() ?? '-' }}</td>
                        </tr>
                    @endforeach
                </x-slot:table>
            </x-nawasara-ui::table>
        @endif

        <div class="mt-6 text-center text-xs text-gray-400 dark:text-neutral-500">
            <a href="{{ url('admin/sync/jobs') }}" wire:navigate class="text-blue-600 hover:underline">
                Lihat semua sync jobs →
            </a>
        </div>
    </x-nawasara-ui::page.container>
</div>
