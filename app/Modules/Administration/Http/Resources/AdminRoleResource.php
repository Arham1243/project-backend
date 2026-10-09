<?php

namespace App\Modules\Administration\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminRoleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status == 1,
            'system' => (bool) $this->system,
            'is_super_admin' => (bool) $this->is_super_admin,
            'assigned_admins_count' => (int) ($this->resource->getAttribute('assigned_admins_count') ?? 0),
            'created_at' => $this->created_at,
        ];
    }
}
