<?php

namespace App\Modules\Administration\Models;

use App\Modules\Administration\Models\User;
use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Validation\ValidationException;
use App\Enums\ActivityLogName;
use App\Traits\AuditsChanges;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use BelongsToCompany;
    use HasUuid;
    use AuditsChanges;

    public function admins()
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function dependencies(): array
    {
        return [
            'admins' => $this->users()->exists(),
        ];
    }

    protected static function booted()
    {
        static::deleting(function (Role $role) {
            if ($role->system) {
                throw ValidationException::withMessages([
                    'role' => 'System roles cannot be deleted.',
                ]);
            }

            $dependencies = $role->dependencies();
            if (in_array(true, $dependencies, true)) {
                throw ValidationException::withMessages([
                    'role' => 'This role is in use and cannot be deleted.',
                ]);
            }
        });
    }

    public function getActivityLogName(): ActivityLogName
    {
        return ActivityLogName::Role;
    }
}
