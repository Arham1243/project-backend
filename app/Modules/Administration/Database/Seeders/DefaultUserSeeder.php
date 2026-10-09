<?php

namespace App\Modules\Administration\Database\Seeders;

use App\Modules\Administration\Models\User;
use App\Modules\Core\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->orderBy('id')->first();
        if (! $company) {
            return;
        }

        User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'admin@project.com'],
            [
                'name' => 'Super Admin',
                'status' => 'active',
                'password' => Hash::make('12345678'),
                'company_id' => $company->id,
            ]
        );
    }
}
