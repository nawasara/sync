<?php

namespace Nawasara\Sync\Livewire\Admin\SyncJobs;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-sync::livewire.pages.admin.sync-jobs.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
