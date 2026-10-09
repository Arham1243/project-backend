<?php

namespace App\Modules\Administration\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Internal admin API login payload (sanctum_admin guard).
 */
class AdminLoginResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => true,
            'message' => 'User logged in successfully',
            'access_token' => $this->access_token ?? null,
            'expires_in' => $this->expires_in ?? null,
        ];
    }
}
