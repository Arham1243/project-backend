<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health';

    protected $description = 'Log when queue workers appear stalled or failed_jobs is non-empty';

    public function handle(): int
    {
        try {
            $this->checkPendingJobs();
            $this->checkFailedJobs();
        } catch (Throwable) {
            //
        }

        return self::SUCCESS;
    }

    private function checkPendingJobs(): void
    {
        if (! Schema::hasTable('jobs')) {
            return;
        }

        $maxAge = (int) config('scheduler.queue_health_pending_max_age', 300);
        $oldest = DB::table('jobs')->min('created_at');
        if ($oldest === null) {
            return;
        }

        $oldestTs = is_numeric($oldest) ? (int) $oldest : strtotime((string) $oldest);
        if ($oldestTs === false) {
            return;
        }

        $age = time() - $oldestTs;
        if ($age >= $maxAge) {
            Log::error('queue_workers_stalled', [
                'oldest_job_age_seconds' => $age,
            ]);
        }
    }

    private function checkFailedJobs(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            return;
        }

        $count = (int) DB::table('failed_jobs')->count();
        if ($count > 0) {
            Log::error('queue_failed_jobs_present', [
                'count' => $count,
            ]);
        }
    }
}
