<?php

namespace App\Support;

use App\Modules\Reports\Jobs\RunScheduledJob;
use App\Modules\Reports\Models\ScheduledJob;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ScheduledJobRegistrar
{
    /** @var list<string> */
    private const SKIP_COMMAND_PREFIXES = [
        'migrate',
        'db:',
        'package:discover',
        'config:',
        'route:',
        'optimize',
    ];

    public function register(Schedule $schedule): void
    {
        $this->registerQueueHealthCheck($schedule);
        $this->registerHeartbeat($schedule);

        if (! $this->shouldLoadDatabaseJobs()) {
            return;
        }

        try {
            $jobs = $this->loadActiveJobDefinitions();
        } catch (Throwable $e) {
            Log::error('scheduled_jobs_registration_failed', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $overlapMinutes = max(1, (int) config('scheduler.overlap_minutes', 10));

        foreach ($jobs as $job) {
            $id = (int) ($job['id'] ?? 0);
            $key = trim((string) ($job['key'] ?? ''));
            $frequency = trim((string) ($job['frequency'] ?? ''));

            if ($id <= 0 || $key === '') {
                continue;
            }

            if (! CronExpression::isValidExpression($frequency)) {
                $this->logInvalidCronOnce($id, $frequency, $key);

                continue;
            }

            $eventName = 'scheduled-job:'.$id;

            $event = $schedule
                ->call(static function () use ($id, $key): void {
                    RunScheduledJob::dispatch($id, $key);
                })
                ->cron($frequency)
                ->name($eventName)
                ->withoutOverlapping($overlapMinutes)
                ->onFailure(function () use ($key, $eventName): void {
                    Log::error('scheduled_job_dispatch_failed', [
                        'event' => $eventName,
                        'key' => $key,
                    ]);
                });

        }
    }

    /**
     * @return list<array{id: int, key: string, frequency: string}>
     */
    private function loadActiveJobDefinitions(): array
    {
        if (! Schema::hasTable('admin_scheduled_jobs')) {
            return [];
        }

        $ttl = (int) config('scheduler.active_jobs_cache_ttl', 60);

        /** @var list<array{id: int, key: string, frequency: string}> $definitions */
        $definitions = Cache::remember(
            ScheduledJob::CACHE_KEY,
            $ttl,
            static function (): array {
                if (! Schema::hasTable('admin_scheduled_jobs')) {
                    return [];
                }

                return ScheduledJob::query()
                    ->where('enabled', true)
                    ->get(['id', 'key', 'frequency'])
                    ->map(static fn (ScheduledJob $job): array => [
                        'id' => (int) $job->id,
                        'key' => (string) $job->key,
                        'frequency' => (string) $job->frequency,
                    ])
                    ->values()
                    ->all();
            }
        );

        return is_array($definitions) ? $definitions : [];
    }

    private function shouldLoadDatabaseJobs(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $command = (string) ($_SERVER['argv'][1] ?? '');

        foreach (self::SKIP_COMMAND_PREFIXES as $prefix) {
            if (str_starts_with($command, $prefix)) {
                return false;
            }
        }

        return str_starts_with($command, 'schedule:');
    }

    private function registerHeartbeat(Schedule $schedule): void
    {
        $url = config('services.healthcheck.url');
        if (! is_string($url) || trim($url) === '') {
            return;
        }

        $schedule->call(static function (): void {
            // No-op heartbeat; external monitor receives pingOnSuccess.
        })
            ->everyMinute()
            ->name('scheduler-heartbeat')
            ->pingOnSuccess(trim($url));
    }

    private function registerQueueHealthCheck(Schedule $schedule): void
    {
        $overlapMinutes = max(1, (int) config('scheduler.overlap_minutes', 10));

        $schedule->command('queue:health')
            ->everyFiveMinutes()
            ->name('queue-health-check')
            ->withoutOverlapping($overlapMinutes);
    }

    private function logInvalidCronOnce(int $id, string $frequency, string $key): void
    {
        $cacheKey = 'scheduled-job-invalid-cron:'.$id.':'.md5($frequency);

        try {
            if (! Cache::add($cacheKey, true, 86400)) {
                return;
            }
        } catch (Throwable) {
            // If cache is down, still log once per process via static? Log anyway.
        }

        Log::error('scheduled_job_invalid_cron', [
            'scheduled_job_id' => $id,
            'key' => $key,
            'frequency' => $frequency,
        ]);
    }
}
