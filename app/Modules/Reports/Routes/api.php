<?php

use App\Modules\Reports\Http\Controllers\ActivityController;
use App\Modules\Reports\Http\Controllers\EmailLogController;
use App\Modules\Reports\Http\Controllers\EmailLogExportController;
use App\Modules\Reports\Http\Controllers\ScheduledJobController;
use Illuminate\Support\Facades\Route;
use Orion\Facades\Orion;

Route::middleware(['auth.any:sanctum_admin', 'check.active'])->group(function () {
    Route::middleware(['role.permission:reports.scheduled-jobs.view'])->group(function () {
        Orion::resource('scheduled-jobs', ScheduledJobController::class, [
            'only' => ['index', 'search', 'show'],
        ])->withoutBatch();
        Route::get('scheduled-jobs/{scheduledJob}/runs', [ScheduledJobController::class, 'runs']);
    });

    Route::middleware(['role.permission:reports.scheduled-jobs.edit'])->group(function () {
        Orion::resource('scheduled-jobs', ScheduledJobController::class, [
            'only' => ['update'],
        ])->withoutBatch();
        Route::post('scheduled-jobs/{scheduledJob}/run', [ScheduledJobController::class, 'run']);
    });

    Route::middleware(['role.permission:reports.email-logs.view'])->group(function () {
        Route::post('email-logs/export', [EmailLogExportController::class, 'export']);
        Orion::resource('email-logs', EmailLogController::class, [
            'only' => ['index', 'search', 'show'],
        ])->withoutBatch();
    });

    Route::middleware(['role.permission:reports.audit-log.view'])->group(function () {
        Orion::resource('activities', ActivityController::class, [
            'only' => ['index', 'search', 'show'],
        ])->withoutBatch();
    });
});
