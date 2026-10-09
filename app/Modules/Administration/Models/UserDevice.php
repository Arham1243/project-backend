<?php

namespace App\Modules\Administration\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuid;

    protected $table = 'admin_user_devices';

    protected $fillable = [
        'company_id',
        'user_id',
        'device_fingerprint',
        'device_name',
        'browser',
        'platform',
        'ip_address',
        'last_used_at',
        'is_verified',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
