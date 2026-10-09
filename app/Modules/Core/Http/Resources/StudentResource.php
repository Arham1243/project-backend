<?php

namespace App\Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'student_id' => $this->student_id,
            'name' => $this->name,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
