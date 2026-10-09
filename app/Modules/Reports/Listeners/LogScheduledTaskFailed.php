<?php

namespace App\Modules\Reports\Listeners;

use App\Modules\Reports\Listeners\Concerns\InteractsWithScheduledJobTasks;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogScheduledTaskFailed
{
    use InteractsWithScheduledJobTasks;

    public function handle(ScheduledTaskFailed $event): void
    {
        try {
            Log::error('scheduled_task_failed', [
                'task' => $this->taskLabel($event->task),
                'error' => $event->exception->getMessage(),
            ]);

            $jobId = $this->scheduledJobIdFromTask($event->task);
            if ($jobId !== null) {
                $this->recordSchedulerLevelRun(
                    $jobId,
                    'failed',
                    $event->exception::class.': '.$event->exception->getMessage()
                );
            }
        } catch (Throwable) {
            //
        }
    }
}
