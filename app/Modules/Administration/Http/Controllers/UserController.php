<?php

namespace App\Modules\Administration\Http\Controllers;
use App\Modules\Administration\Http\Controllers\AdminOrionController;

use App\Modules\Administration\Http\Requests\UserRequest;
use App\Modules\Administration\Http\Resources\UserResource;
use App\Modules\Administration\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Modules\Administration\Models\Admin\AdminEmailTemplate;
use App\Modules\Administration\Services\AdminAuthMailer;
use Illuminate\Support\Str;
use Orion\Concerns\DisableAuthorization;
use Orion\Http\Requests\Request as OrionRequest;
use App\Modules\Administration\Models\Role;

class UserController extends AdminOrionController
{
    use DisableAuthorization;

    protected $model = User::class;

    protected $request = UserRequest::class;

    protected $resource = UserResource::class;

    public function searchableBy(): array
    {
        return ['name', 'email'];
    }

    public function sortableBy(): array
    {
        return ['name', 'email', 'status', 'created_at'];
    }

    public function filterableBy(): array
    {
        return ['name', 'email', 'status', 'role.name'];
    }

    public function includes(): array
    {
        return ['role'];
    }

    public function alwaysIncludes(): array
    {
        return ['role'];
    }

    protected function beforeSave(OrionRequest $request, Model $entity)
    {
        if (! $entity->exists) {
            $entity->status = 'pending';
            if (! $request->filled('password')) {
                $entity->password = Hash::make(Str::random(64));
            }
            if (blank($entity->timezone)) {
                $entity->timezone = $request->input('timezone') ?: 'America/New_York';
            }
        }

        $actor = $request->user();
        if ($actor && $actor->company_id) {
            $entity->company_id = $actor->company_id;
        }

        if ($request->filled('password')) {
            $entity->password = Hash::make($request->input('password'));
        }

        if ($request->filled('role_id')) {
            $role = Role::where('guard_name', 'sanctum')->findOrFail($request->input('role_id'));
            $entity->role_id = $role->id;
        }
    }

    protected function afterSave(OrionRequest $request, Model $entity)
    {
        if ($entity->role_id) {
            $role = Role::find($entity->role_id);
            if ($role) {
                $entity->syncRoles([$role]);
            }
        }

        if ($entity->wasRecentlyCreated && $entity->status === 'pending') {
            $this->sendPasswordSetupEmail($entity);
        }
    }

    protected function sendPasswordSetupEmail(User $admin): void
    {
        $token = Str::random(64);

        DB::table('admin_password_reset_tokens')->updateOrInsert(
            ['email' => $admin->email],
            [
                'token' => $token,
                'created_at' => now(),
            ]
        );

        $base = rtrim((string) config('app.frontend_url'), '/');
        $verifyLink = $base.'/auth/password/set?token='.urlencode($token);

        try {
            app(AdminAuthMailer::class)->send(
                AdminEmailTemplate::SLUG_SETUP_PASSWORD,
                $admin->email,
                [
                    'name' => $admin->name ?: $admin->email,
                    'verify_link' => $verifyLink,
                ],
                $admin->id
            );
        } catch (\Throwable $e) {
            Log::error('Admin password setup email failed for admin '.$admin->id.': '.$e->getMessage());
        }
    }

    public function changeStatus(User $admin)
    {
        $newStatus = $admin->status === 'active' ? 'inactive' : 'active';
        $admin->update(['status' => $newStatus]);

        return new UserResource($admin->fresh());
    }

    public function deleteUser(User $admin)
    {
        $admin->tokens()->delete();
        $admin->delete();

        return response()->json(['message' => 'Admin deleted successfully.']);
    }
}
