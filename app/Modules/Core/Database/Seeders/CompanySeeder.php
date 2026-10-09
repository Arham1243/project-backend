<?php

namespace App\Modules\Core\Database\Seeders;

use App\Modules\Core\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->firstOrCreate(
            ['name' => 'Kumon'],
            [
                'timezone' => 'America/New_York',
                'date_format' => 'mm/dd/yy',
            ]
        );
    }
}
