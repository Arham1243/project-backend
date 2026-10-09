<?php

namespace App\Modules\Reports\Listeners;

use App\Modules\Reports\Listeners\Concerns\InteractsWithScheduledJobTasks;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Throwable;

class LogScheduledTaskSkipped
{
    use InteractsWithScheduledJobTasks;

    public function handle(ScheduledTaskSkipped $event): void
    {
        try {
            $jobId = $this->scheduledJobIdFromTask($event->task);
            if ($jobId === null) {
                return;
            }

            $this->recordSchedulerLevelRun(
                $jobId,
                'skipped',
                'Scheduled task was skipped (overlap lock).'
            );
        } catch (Throwable) {
            //
        }
    }
}
