<?php

namespace App\Modules\Reports\Http\Resources;

use App\Modules\Reports\Models\ScheduledJobRun;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduledJobRunResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'scheduled_job_id' => $this->scheduled_job_id,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration' => $this->duration !== null ? (float) $this->duration : null,
            'status' => $this->status,
            'trigger' => $this->trigger ?? ScheduledJobRun::TRIGGER_SCHEDULE,
            'triggered_by' => $this->triggered_by,
            'output' => $this->output,
            'created_at' => $this->created_at,
        ];
    }
}
