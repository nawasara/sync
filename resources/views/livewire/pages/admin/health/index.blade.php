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

        {{-- Top-level totals — refactored ke design-system stat-card.
             Color logic:
             - Total Jobs: primary (informational, count)
             - Success: success (semantic — green-600 cerah, beda dari brand emerald)
             - Failed: danger (rose) kalau >0, neutral kalau 0
             - Pending: info (cyan) kalau >0, neutral kalau 0 — pending bukan
               kondisi error, cuma "lagi diproses". --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <x-nawasara-ui::stat-card
                label="Total Jobs"
                :value="number_format($this->totals['total'])"
                icon="lucide-activity"
                color="primary"
                :description="'window '.$windowHours.'h'"
                accent />

            <x-nawasara-ui::stat-card
                label="Success"
                :value="number_format($this->totals['success'])"
                icon="lucide-circle-check"
                color="success"
                accent />

            <x-nawasara-ui::stat-card
                label="Failed / Conflict"
                :value="number_format($this->totals['failed'])"
                icon="lucide-circle-x"
                :color="$this->totals['failed'] > 0 ? 'danger' : 'neutral'"
                :description="$this->totals['failed'] > 0 ? 'perlu attention' : 'semua aman'"
                accent />

            <x-nawasara-ui::stat-card
                label="Pending"
                :value="number_format($this->totals['pending'])"
                icon="lucide-clock"
                :color="$this->totals['pending'] > 0 ? 'info' : 'neutral'"
                :description="$this->totals['pending'] > 0 ? 'lagi diproses' : 'queue kosong'"
                accent />
        </div>

        {{-- Per-service cards --}}
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Per Service</h2>
        @if (empty($this->services))
            {{-- Premium empty state — match pattern dari dashboard /home, B2-B7 --}}
            <div class="text-center py-16 px-6 border-2 border-dashed border-gray-200 dark:border-neutral-700 rounded-xl bg-gray-50/50 dark:bg-neutral-900/40 mb-6">
                <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gray-100 dark:bg-neutral-800 mb-4">
                    <x-lucide-database class="size-7 text-gray-400 dark:text-neutral-500" />
                </div>
                <p class="text-base font-semibold text-gray-800 dark:text-neutral-200">
                    Belum ada sync job dalam window ini
                </p>
                <p class="mt-2 text-sm text-gray-500 dark:text-neutral-400 max-w-sm mx-auto">
                    Tidak ada aktivitas sinkronisasi dalam {{ $windowHours }} jam terakhir. Coba pilih window lebih panjang di kanan atas.
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                @foreach ($this->services as $svc)
                    @php
                        // Success rate badge color — pakai semantic threshold:
                        // 95%+ = green (sehat), 80-94% = amber (warning), <80% = rose (degraded)
                        $rate = $svc['success_rate'];
                        $rateBadgeClass = match (true) {
                            $rate === null => 'bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400',
                            $rate >= 95 => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                            $rate >= 80 => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                            default => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
                        };
                    @endphp
                    <div class="p-5 rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 transition-shadow hover:shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-base font-bold text-gray-900 dark:text-white capitalize">{{ $svc['service'] }}</h3>
                            @if ($rate !== null)
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $rateBadgeClass }}">
                                    {{ $rate }}%
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center text-xs mb-3">
                            <div>
                                <div class="font-bold text-green-600 dark:text-green-400">{{ $svc['success'] }}</div>
                                <div class="text-gray-500 dark:text-neutral-400">Success</div>
                            </div>
                            <div>
                                <div class="font-bold {{ ($svc['failed'] + $svc['conflict']) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">{{ $svc['failed'] + $svc['conflict'] }}</div>
                                <div class="text-gray-500 dark:text-neutral-400">Failed</div>
                            </div>
                            <div>
                                <div class="font-bold {{ $svc['pending'] > 0 ? 'text-cyan-600 dark:text-cyan-400' : 'text-gray-400' }}">{{ $svc['pending'] }}</div>
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
                                    <span class="text-amber-700 dark:text-amber-400">belum pernah</span>
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
            {{-- "All clean" celebratory state — beda dari empty state error.
                 Pakai emerald tint subtle untuk signal positif. --}}
            <div class="p-6 rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/40 dark:bg-emerald-900/10 text-center">
                <div class="inline-flex items-center justify-center size-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 mb-2">
                    <x-lucide-circle-check class="size-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300">Tidak ada failure terbaru</p>
                <p class="text-xs text-gray-500 dark:text-neutral-400 mt-0.5">Semua sync job berjalan bersih dalam window ini.</p>
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
                                <span class="text-xs text-rose-600 dark:text-rose-400 font-mono truncate block max-w-md" title="{{ $job->error }}">{{ \Illuminate\Support\Str::limit($job->error, 80) }}</span>
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-500 dark:text-neutral-400">{{ $job->finished_at?->diffForHumans() ?? '-' }}</td>
                        </tr>
                    @endforeach
                </x-slot:table>
            </x-nawasara-ui::table>
        @endif

        <div class="mt-6 text-center text-xs text-gray-400 dark:text-neutral-500">
            <a href="{{ url('admin/sync/jobs') }}" wire:navigate class="text-emerald-700 dark:text-emerald-400 hover:underline font-medium">
                Lihat semua sync jobs →
            </a>
        </div>
    </x-nawasara-ui::page.container>
</div>
