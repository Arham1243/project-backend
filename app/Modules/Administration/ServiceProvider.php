<?php

namespace App\Modules\Administration;

use App\Support\ModulePermissionConfig;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/Config/permission_matrix.php',
            'permission_matrix'
        );

        config([
            'permission_matrix' => ModulePermissionConfig::merge(
                config('permission_matrix', [])
            ),
        ]);

        $migrations = __DIR__.'/Database/Migrations';
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/Routes/api.php');
    }
}
