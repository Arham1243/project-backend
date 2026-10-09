<?php

namespace App\Modules\Administration\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginOtp extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuid;

    protected $table = 'admin_user_login_otps';

    protected $fillable = [
        'company_id',
        'user_id',
        'device_fingerprint',
        'otp_code',
        'session_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function expirationMinutes(): int
    {
        $minutes = (int) config('auth.otp.expiration_minutes', 10);

        return $minutes > 0 ? $minutes : 10;
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->lte(now());
    }
}
