<?php

namespace App\Modules\Reports\Database\Seeders;

use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public int $order = 30;

    public function run(): void
    {
        $this->call([
            ScheduledJobSeeder::class,
        ]);
    }
}
