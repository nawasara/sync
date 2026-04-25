<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Pengaturan', 'url' => '#'], ['label' => 'Sync Jobs']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Sync Jobs</x-nawasara-ui::page.title>

        @livewire('nawasara-sync.admin.sync-jobs.section.table')
    </x-nawasara-ui::page.container>
</div>
