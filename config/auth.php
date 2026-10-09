<?php

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'sanctum_admin'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'admins'),
    ],

    'guards' => [
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
        'sanctum_admin' => [
            'driver' => 'sanctum',
            'provider' => 'admins',
        ],
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Modules\Administration\Models\User::class,
        ],
    ],

    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'admin_password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    'otp' => [
        'fallback_enabled' => env('OTP_FALLBACK_ENABLED', false),
        'fallback_code' => env('OTP_FALLBACK_CODE', '083078'),
        'expiration_minutes' => 10,
    ],
];
