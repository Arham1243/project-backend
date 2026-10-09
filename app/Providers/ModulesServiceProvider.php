<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Registers every module service provider at App\Modules\{Module}\ServiceProvider.
 */
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->discoverModuleProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    private function discoverModuleProviders(): array
    {
        $providers = [];

        foreach (glob(app_path('Modules/*/ServiceProvider.php')) ?: [] as $path) {
            $module = basename(dirname($path));
            if ($module === '' || ! Str::isAscii($module)) {
                continue;
            }

            $class = "App\\Modules\\{$module}\\ServiceProvider";

            if (class_exists($class)) {
                $providers[] = $class;
            }
        }

        sort($providers, SORT_STRING);

        return $providers;
    }
}
