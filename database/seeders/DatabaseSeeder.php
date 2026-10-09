<?php

namespace Database\Seeders;

use App\Support\ModuleSeederDiscovery;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ModuleSeederDiscovery::moduleSeederClasses() as $seederClass) {
            $this->call($seederClass);
        }
    }
}
