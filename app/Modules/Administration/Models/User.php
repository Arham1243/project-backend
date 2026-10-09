<?php

namespace App\Modules\Administration\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Enums\ActivityLogName;
use App\Traits\AuditsChanges;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use BelongsToCompany;
    use HasApiTokens;
    use HasRoles;
    use HasUuid;
    use AuditsChanges;

    protected $table = 'admin_users';

    protected $guard_name = 'sanctum';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'dark_mode' => 'boolean',
            'table_preferences' => 'array',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function getActivityLogName(): ActivityLogName
    {
        return ActivityLogName::User;
    }

    protected function activityLogExcept(): array
    {
        return ['password', 'updated_at', 'created_at'];
    }
}
