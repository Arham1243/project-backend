<?php

namespace App\Modules\Administration\Http\Resources;

use App\Http\Resources\Concerns\OmitsRedundantKeys;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'timezone' => $this->timezone ?: 'America/New_York',
            'role' => $this->whenLoaded('role', function () {
                return [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                ];
            }),
        ];

        if (! $this->relationLoaded('role') || $this->role === null) {
            $data['role_id'] = $this->role_id;
        }

        return $this->omitRedundantRelationKeys($data, [
            ['role_id', 'role'],
        ]);
    }
}
