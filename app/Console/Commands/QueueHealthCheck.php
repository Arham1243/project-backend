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

    protected $description = 'Log when failed_jobs is non-empty';

    public function handle(): int
    {
        try {
            $this->checkFailedJobs();
        } catch (Throwable) {
            //
        }

        return self::SUCCESS;
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
