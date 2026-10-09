<?php

namespace App\Modules\Reports\Listeners\Concerns;

use App\Modules\Reports\Models\ScheduledJob;
use App\Modules\Reports\Models\ScheduledJobRun;
use Throwable;

trait InteractsWithScheduledJobTasks
{
    protected function taskLabel(object $task): string
    {
        if (property_exists($task, 'description') && is_string($task->description) && $task->description !== '') {
            return $task->description;
        }

        if (method_exists($task, 'getSummaryForDisplay')) {
            return (string) $task->getSummaryForDisplay();
        }

        return 'unknown';
    }

    protected function scheduledJobIdFromTask(object $task): ?int
    {
        $label = $this->taskLabel($task);
        if (! str_starts_with($label, 'scheduled-job:')) {
            return null;
        }

        $id = (int) substr($label, strlen('scheduled-job:'));

        return $id > 0 ? $id : null;
    }

    protected function recordSchedulerLevelRun(int $scheduledJobId, string $status, string $output): void
    {
        try {
            if (! ScheduledJob::query()->whereKey($scheduledJobId)->exists()) {
                return;
            }

            $now = now();

            ScheduledJobRun::query()->create([
                'scheduled_job_id' => $scheduledJobId,
                'started_at' => $now,
                'finished_at' => $now,
                'duration' => 0,
                'status' => $status,
                'output' => $output,
            ]);
        } catch (Throwable) {
            //
        }
    }
}
