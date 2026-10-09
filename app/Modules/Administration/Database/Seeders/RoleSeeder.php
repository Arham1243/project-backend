<?php

namespace App\Modules\Administration\Database\Seeders;

use App\Modules\Administration\Models\User;
use App\Modules\Administration\Models\Role;
use App\Modules\Core\Models\Company;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->orderBy('id')->first();
        if (! $company) {
            return;
        }

        $superAdmin = Role::withoutGlobalScopes()->firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'sanctum',
            'company_id' => $company->id,
        ], [
            'system' => true,
            'is_super_admin' => true,
        ]);

        $superAdmin->update([
            'system' => true,
            'is_super_admin' => true,
        ]);

        $allPermissions = Permission::where('guard_name', 'sanctum')->get();
        $superAdmin->syncPermissions($allPermissions);

        $admin = User::withoutGlobalScopes()->where('email', 'admin@project.com')->first();
        if ($admin) {
            $admin->update([
                'role_id' => $superAdmin->id,
                'company_id' => $company->id,
            ]);
            $admin->syncRoles([$superAdmin]);
        }
    }
}
