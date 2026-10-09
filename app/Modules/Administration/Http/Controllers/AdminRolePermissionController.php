<?php

namespace App\Modules\Administration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administration\Models\Role;
use App\Modules\Administration\Support\PermissionMatrix;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class AdminRolePermissionController extends Controller
{
    /** @var list<string> Permission name prefixes included in the role matrix. */
    private array $adminModulePrefixes = [
        'administration',
        'reports',
    ];

    /** @return array<string, list<string>> */
    private function disabledMatrixActions(): array
    {
        return config('permission_matrix.disabled_matrix_actions', []);
    }

    public function show(Role $role)
    {
        if ($role->guard_name !== 'sanctum') {
            abort(404);
        }

        $allPerms = Permission::where('guard_name', 'sanctum')
            ->where(function ($q) {
                foreach ($this->adminModulePrefixes as $prefix) {
                    $q->orWhere('name', 'like', $prefix.'.%');
                }
            })
            ->get();
        $rolePerms = $role->getAllPermissions()->pluck('name')->toArray();

        $matrix = [];

        foreach ($allPerms as $perm) {
            if (! PermissionMatrix::isApplicablePermissionName($perm->name)) {
                continue;
            }

            $parts = explode('.', $perm->name);
            $action = array_pop($parts);
            $entity = implode('.', $parts);

            if (! isset($matrix[$entity])) {
                $matrix[$entity] = [
                    'view' => false,
                    'create' => false,
                    'edit' => false,
                    'delete' => false,
                ];

                foreach ($this->disabledMatrixActions()[$entity] ?? [] as $disabled) {
                    $matrix[$entity][$disabled] = null;
                }
            }

            if (array_key_exists($action, $matrix[$entity]) && $matrix[$entity][$action] === null) {
                continue;
            }

            $matrix[$entity][$action] = in_array($perm->name, $rolePerms);
        }

        foreach ($matrix as $entity => $actions) {
            $matrix[$entity] = PermissionMatrix::applyDependenciesForEntity(
                $entity,
                $actions
            );
        }

        return response()->json($matrix);
    }

    public function sync(Request $request, Role $role)
    {
        if ($role->guard_name !== 'sanctum') {
            abort(404);
        }

        $permissionNames = PermissionMatrix::expandPermissionNames(
            collect($request->input('permissions', []))
                ->filter(function ($name) {
                    return is_string($name)
                        && $name !== ''
                        && PermissionMatrix::isApplicablePermissionName($name);
                })
                ->values()
                ->toArray()
        );

        $role->syncPermissions($permissionNames);

        return response()->json(['data' => $role->getPermissionNames()]);
    }
}
