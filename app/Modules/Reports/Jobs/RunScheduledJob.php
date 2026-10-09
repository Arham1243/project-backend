<?php

namespace App\Modules\Reports\Jobs;

use App\Modules\Reports\Models\ScheduledJob;
use App\Modules\Reports\Models\ScheduledJobRun;
use App\Modules\Reports\Services\ScheduledJobRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunScheduledJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $scheduledJobId,
        public string $jobKey,
    ) {
        $this->onQueue('scheduled');
    }

    public function timeout(): int
    {
        return max(30, (int) config('scheduler.job_timeout', 120));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $timeout = $this->timeout();

        return [
            (new WithoutOverlapping('scheduled-job:'.$this->jobKey))
                ->expireAfter($timeout + 30)
                ->dontRelease(),
        ];
    }

    /**
     * Cache lock key used by {@see WithoutOverlapping} for this job key.
     */
    public static function overlapLockKey(string $jobKey): string
    {
        return 'laravel-queue-overlap:'.self::class.':scheduled-job:'.$jobKey;
    }

    public function handle(ScheduledJobRunner $runner): void
    {
        $job = ScheduledJob::query()->find($this->scheduledJobId);

        if ($job === null || ! $job->enabled) {
            return;
        }

        $runner->run($job, ScheduledJobRun::TRIGGER_SCHEDULE);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('run_scheduled_job_queue_failed', [
            'scheduled_job_id' => $this->scheduledJobId,
            'key' => $this->jobKey,
            'error' => $exception?->getMessage(),
        ]);
    }
}
