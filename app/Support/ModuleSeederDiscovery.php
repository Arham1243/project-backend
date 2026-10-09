<?php

namespace App\Support;

use Illuminate\Database\Seeder;
use ReflectionClass;

class ModuleSeederDiscovery
{
    /**
     * @return list<class-string<Seeder>>
     */
    public static function moduleSeederClasses(): array
    {
        $classes = [];

        foreach (glob(app_path('Modules/*/Database/Seeders/*ModuleSeeder.php')) ?: [] as $path) {
            $class = self::classFromPath($path);
            if ($class !== null && is_subclass_of($class, Seeder::class)) {
                $classes[] = $class;
            }
        }

        usort($classes, function (string $a, string $b): int {
            return self::orderFor($a) <=> self::orderFor($b);
        });

        return $classes;
    }

    private static function orderFor(string $class): int
    {
        if (! class_exists($class)) {
            return 100;
        }

        $reflection = new ReflectionClass($class);
        if (! $reflection->hasProperty('order')) {
            return 100;
        }

        $property = $reflection->getProperty('order');
        $property->setAccessible(true);
        $instance = $reflection->newInstanceWithoutConstructor();
        $order = $property->getValue($instance);

        return is_int($order) ? $order : 100;
    }

    /**
     * @return class-string<Seeder>|null
     */
    private static function classFromPath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', $path);
        $appRoot = str_replace('\\', '/', app_path()).'/';
        if (! str_starts_with($normalized, $appRoot)) {
            return null;
        }

        $relative = substr($normalized, strlen($appRoot));
        if (str_ends_with($relative, '.php')) {
            $relative = substr($relative, 0, -4);
        }
        $relative = str_replace('/', '\\', $relative);

        $class = 'App\\'.$relative;

        return class_exists($class) ? $class : null;
    }
}
