<?php

namespace App\Modules\Reports;

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
