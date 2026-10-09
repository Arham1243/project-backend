<?php

namespace App\Modules\_Skeleton\Database\Seeders;

use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public int $order = 95;

    public function run(): void
    {
        $this->call([
            // ExampleSeeder::class,
        ]);
    }
}
