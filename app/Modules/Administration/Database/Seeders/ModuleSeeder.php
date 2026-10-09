<?php

namespace App\Modules\Administration\Database\Seeders;

use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public int $order = 10;

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            DefaultUserSeeder::class,
            EmailTemplateSeeder::class,
        ]);
    }
}
