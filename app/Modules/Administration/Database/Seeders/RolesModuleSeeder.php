<?php

namespace App\Modules\Administration\Database\Seeders;

use Illuminate\Database\Seeder;

class RolesModuleSeeder extends Seeder
{
    public int $order = 90;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);
    }
}
