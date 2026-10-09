<?php

namespace App\Modules\Administration\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminGroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'members_count' => $this->whenCounted('users'),
        ];
    }
}
