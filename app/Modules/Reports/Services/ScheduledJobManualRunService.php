<?php

namespace App\Modules\Reports\Services;

use App\Enums\ActivityLogName;
use App\Modules\Reports\Jobs\RunScheduledJob;
use App\Modules\Reports\Models\ScheduledJob;
use App\Modules\Reports\Models\ScheduledJobRun;
use App\Modules\Administration\Models\User;
use Illuminate\Support\Facades\Cache;

class ScheduledJobManualRunService
{
    public function __construct(
        private readonly ScheduledJobRunner $runner,
    ) {}

    /**
     * Run a scheduled job on demand. Uses the same overlap key as the queue worker.
     *
     * @return array{run: ScheduledJobRun}|null null when the job is already running
     */
    public function run(ScheduledJob $job, int $adminId): ?array
    {
        $timeout = max(30, (int) config('scheduler.job_timeout', 120));
        $lockSeconds = $timeout + 30;
        $lockKey = RunScheduledJob::overlapLockKey($job->key);

        $lock = Cache::lock($lockKey, $lockSeconds);

        if (! $lock->get()) {
            return null;
        }

        try {
            @set_time_limit($timeout + 15);

            $run = $this->runner->run(
                $job,
                ScheduledJobRun::TRIGGER_MANUAL,
                $adminId,
            );

            $this->logManualRun($job, $run, $adminId);

            return ['run' => $run];
        } finally {
            $lock->release();
        }
    }

    private function logManualRun(ScheduledJob $job, ScheduledJobRun $run, int $adminId): void
    {
        $causer = User::query()->find($adminId);

        $logger = activity(ActivityLogName::ScheduledJob->value)
            ->performedOn($job)
            ->event('manual_run')
            ->withProperties([
                'subject_label' => $job->name,
                'run_status' => $run->status,
                'scheduled_job_run_id' => $run->id,
            ]);

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        $logger->log('Ran Scheduled Job');
    }
}
