<?php

namespace App\Modules\Administration\Http\Controllers;
use App\Modules\Administration\Http\Controllers\AdminOrionController;

use App\Modules\Administration\Http\Requests\AdminRoleRequest;
use App\Modules\Administration\Http\Resources\AdminRoleResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Modules\Administration\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Orion\Concerns\DisableAuthorization;
use Orion\Http\Requests\Request as OrionRequest;

class AdminRoleController extends AdminOrionController
{
    use DisableAuthorization;

    protected $model = Role::class;

    protected $request = AdminRoleRequest::class;

    protected $resource = AdminRoleResource::class;

    public function searchableBy(): array
    {
        return ['name'];
    }

    public function sortableBy(): array
    {
        return ['name', 'status', 'created_at'];
    }

    public function filterableBy(): array
    {
        return ['name', 'status'];
    }

    protected function buildIndexFetchQuery(Request $request, array $requestedRelations): Builder
    {
        $query = parent::buildIndexFetchQuery($request, $requestedRelations);
        $query->withCount(['admins as assigned_admins_count']);

        return $query;
    }

    protected function buildShowFetchQuery(Request $request, array $requestedRelations): Builder
    {
        $query = parent::buildShowFetchQuery($request, $requestedRelations);
        $query->withCount(['admins as assigned_admins_count']);

        return $query;
    }

    protected function beforeSave(\Orion\Http\Requests\Request $request, \Illuminate\Database\Eloquent\Model $entity)
    {
        $entity->guard_name = 'sanctum';

        $user = $request->user();
        if ($user && $user->company_id && empty($entity->company_id)) {
            $entity->company_id = $user->company_id;
        }
    }

    protected function beforeUpdate(OrionRequest $request, Model $entity): void
    {
        if (! $entity instanceof Role) {
            return;
        }

        if (! $entity->system || ! $entity->is_super_admin || ! $request->has('name')) {
            return;
        }

        $newName = trim((string) $request->input('name'));
        if ($newName !== (string) $entity->name) {
            throw ValidationException::withMessages([
                'name' => 'This role name cannot be changed.',
            ]);
        }
    }

    public function changeStatus(Role $role)
    {
        if ((bool) $role->status) {
            $assigned = DB::table('admin_users')->where('role_id', $role->id)->exists();
            if ($assigned) {
                throw ValidationException::withMessages([
                    'role' => 'This role is assigned to one or more admins and cannot be deactivated.',
                ]);
            }
        }

        $role->update(['status' => ! $role->status]);
        $role->loadCount(['admins as assigned_admins_count']);

        return new AdminRoleResource($role);
    }

    public function deleteRole(Role $role)
    {
        if ($role->guard_name !== 'sanctum') {
            abort(404);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully.']);
    }
}
