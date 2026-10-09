<?php

namespace App\Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public int $order = 5;

    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
        ]);
    }
}
