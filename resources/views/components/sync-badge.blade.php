@props([
    'status' => 'synced',     // synced, pending_create, pending_update, pending_delete, failed, conflict
    'error' => null,
    'size' => 'sm',           // sm, xs
])

@php
    $config = match ($status) {
        'synced' => [
            'icon' => 'lucide-check-circle',
            'class' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            'label' => 'Synced',
        ],
        'pending_create' => [
            'icon' => 'lucide-clock',
            'class' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 animate-pulse',
            'label' => 'Creating...',
        ],
        'pending_update' => [
            'icon' => 'lucide-clock',
            'class' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 animate-pulse',
            'label' => 'Saving...',
        ],
        'pending_delete' => [
            'icon' => 'lucide-clock',
            'class' => 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 animate-pulse',
            'label' => 'Deleting...',
        ],
        'failed' => [
            'icon' => 'lucide-alert-circle',
            'class' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'label' => 'Failed',
        ],
        'conflict' => [
            'icon' => 'lucide-alert-triangle',
            'class' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            'label' => 'Conflict',
        ],
        default => [
            'icon' => 'lucide-help-circle',
            'class' => 'bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400',
            'label' => ucfirst($status),
        ],
    };

    $sizeClass = $size === 'xs'
        ? 'px-1.5 py-0.5 text-[10px] gap-1'
        : 'px-2 py-0.5 text-xs gap-1';

    $iconSize = $size === 'xs' ? 'size-3' : 'size-3.5';
@endphp

<span @class([
        'inline-flex items-center rounded-full font-medium',
        $sizeClass,
        $config['class'],
    ])
    @if ($error) title="{{ $error }}" @endif>
    <x-dynamic-component :component="$config['icon']" :class="$iconSize" />
    {{ $config['label'] }}
</span>
