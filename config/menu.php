<?php

$prefix = 'admin/sync';

return [
    [
        'workspace' => 'settings',
        'label' => 'Pengaturan',
        'icon' => 'lucide-settings',
        'url' => '',
        'permission' => 'sync.job.view',
        'submenu' => [
            [
                'label' => 'Sync Jobs',
                'icon' => 'lucide-refresh-cw',
                'url' => url($prefix.'/jobs'),
                'permission' => 'sync.job.view',
                'navigate' => true,
            ],
        ],
    ],
];
