<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth.any:sanctum_admin', 'check.active'])->group(function () {
    Route::middleware(['role.permission:example.items.view'])->group(function () {
        // Route::get('examples', [ExampleController::class, 'index']);
        // Route::post('examples/search', [ExampleController::class, 'search']);
    });
});
