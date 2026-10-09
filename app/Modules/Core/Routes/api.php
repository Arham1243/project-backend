<?php

use App\Modules\Core\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;
use Orion\Facades\Orion;

Route::middleware(['auth.any:sanctum_admin', 'check.active'])->group(function () {
    Route::middleware(['role.permission:core.students.view'])->group(function () {
        Orion::resource('students', StudentController::class, [
            'only' => ['index', 'search', 'show'],
        ])->withoutBatch();
    });

    Route::middleware(['role.permission:core.students.create'])->group(function () {
        Orion::resource('students', StudentController::class, [
            'only' => ['store'],
        ])->withoutBatch();
    });

    Route::middleware(['role.permission:core.students.edit'])->group(function () {
        Orion::resource('students', StudentController::class, [
            'only' => ['update'],
        ])->withoutBatch();
        Route::post('students/{student}/change-status', [StudentController::class, 'changeStatus']);
    });

    Route::middleware(['role.permission:core.students.delete'])->group(function () {
        Route::delete('students/{student}/delete', [StudentController::class, 'deleteStudent']);
    });
});
