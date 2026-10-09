<?php

namespace App\Modules\Reports\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ScheduledJobResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'frequency' => $this->frequency,
            'enabled' => (bool) $this->enabled,
            'last_run_at' => $this->last_run_at,
            'last_run_status' => $this->last_run_status,
            'last_run_duration' => $this->last_run_duration !== null
                ? (float) $this->last_run_duration
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
