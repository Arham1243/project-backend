<?php

namespace App\Modules\Administration\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminGroupMemberResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->whenLoaded('role', function () {
                return $this->role ? [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                ] : null;
            }),
        ];
    }
}
