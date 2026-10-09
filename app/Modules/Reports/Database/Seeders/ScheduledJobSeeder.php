<?php

namespace App\Modules\Reports\Database\Seeders;

use App\Modules\Reports\Http\Controllers\Cron\SyncEmailLogsController;
use App\Modules\Reports\Models\ScheduledJob;
use Illuminate\Database\Seeder;

class ScheduledJobSeeder extends Seeder
{
    public function run(): void
    {
        $jobs = [
            [
                'key' => 'sync-email-logs',
                'name' => 'Sync Email Logs',
                'description' => 'Pulls recent Mailgun events to keep admin portal email logs up to date.',
                'handler' => SyncEmailLogsController::class,
                'endpoint' => '/api/cron/sync-email-logs',
                'token_config_key' => 'services.mailgun_email_log_sync.token',
                'frequency' => '*/15 * * * *',
            ],
        ];

        foreach ($jobs as $job) {
            ScheduledJob::query()->updateOrCreate(
                ['key' => $job['key']],
                [
                    'name' => $job['name'],
                    'description' => $job['description'],
                    'handler' => $job['handler'],
                    'endpoint' => $job['endpoint'],
                    'token_config_key' => $job['token_config_key'],
                    'frequency' => $job['frequency'],
                    'enabled' => true,
                ]
            );
        }

        $managedKeys = array_column($jobs, 'key');
        ScheduledJob::query()
            ->whereNotIn('key', $managedKeys)
            ->delete();

        ScheduledJob::flushCache();
    }
}
