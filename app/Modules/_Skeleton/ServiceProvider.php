<?php

/**
 * Starter module — copy to app/Modules/YourModule, update the namespace, and replace example.items.
 * Auto-registered via ModulesServiceProvider (same as other modules under app/Modules).
 */
namespace App\Modules\_Skeleton;

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
        $migrations = __DIR__.'/Database/Migrations';
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/Routes/api.php');
    }
}
