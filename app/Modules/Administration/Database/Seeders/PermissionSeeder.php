<?php

namespace App\Modules\Administration\Database\Seeders;

use App\Modules\Administration\Support\PermissionMatrix;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $defaultActions = config(
            'permission_matrix.default_actions',
            ['view', 'create', 'edit', 'delete']
        );
        $entityActions = config('permission_matrix.entity_actions', []);

        $fullCrudModules = config('permission_matrix.full_crud_entities', []);

        foreach ($fullCrudModules as $module) {
            foreach ($defaultActions as $action) {
                Permission::firstOrCreate([
                    'name' => "$module.$action",
                    'guard_name' => 'sanctum',
                ]);
            }
        }

        foreach ($entityActions as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "$module.$action",
                    'guard_name' => 'sanctum',
                ]);
            }
        }

        $allowed = PermissionMatrix::allDefinedPermissionNames();

        Permission::query()
            ->where('guard_name', 'sanctum')
            ->whereNotIn('name', $allowed)
            ->delete();
    }
}
