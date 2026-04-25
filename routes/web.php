<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Sync\Livewire\Admin\SyncJobs\Index as SyncJobsIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('admin/sync')->group(function () {
    Route::get('jobs', SyncJobsIndex::class)
        ->middleware(PermissionMiddleware::using('sync.job.view'))
        ->name('nawasara-sync.jobs.index');
});
