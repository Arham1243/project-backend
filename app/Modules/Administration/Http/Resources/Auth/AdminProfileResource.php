<?php

namespace App\Modules\Administration\Http\Resources\Auth;

use App\Http\Resources\Concerns\OmitsRedundantKeys;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProfileResource extends JsonResource
{
    use OmitsRedundantKeys;

    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'profile_image' => $this->profile_image,
            'dark_mode' => (bool) ($this->dark_mode ?? false),
            'timezone' => $this->timezone ?: 'America/New_York',
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'is_super_admin' => (bool) ($this->role->is_super_admin ?? false),
            ] : null,
        ];

        if ($data['role'] === null) {
            $data['role_id'] = $this->role_id;
        }

        return $this->omitIfRelationPresent($data, 'role_id', 'role');
    }
}
