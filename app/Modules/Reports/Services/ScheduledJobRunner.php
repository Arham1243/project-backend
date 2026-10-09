<?php

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Models\ScheduledJob;
use App\Modules\Reports\Models\ScheduledJobRun;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Executes a managed scheduled job by directly invoking its cron controller.
 *
 * Each scheduled_jobs row stores the FQCN of an existing invokable cron controller
 * along with the config key for the token that controller expects. The runner builds
 * a synthetic Request carrying that token, invokes the controller, captures the
 * response/exception, and writes a scheduled_job_runs log entry while updating the
 * job's last_run_* summary columns.
 */
class ScheduledJobRunner
{
    /**
     * Maximum stored output length per run (longer outputs are truncated with a marker).
     */
    private const MAX_OUTPUT_LENGTH = 20_000;

    public function run(
        ScheduledJob $job,
        string $trigger = ScheduledJobRun::TRIGGER_SCHEDULE,
        ?int $triggeredBy = null,
    ): ScheduledJobRun {
        $startedAt = Carbon::now();
        $start = microtime(true);

        $run = ScheduledJobRun::create([
            'scheduled_job_id' => $job->id,
            'started_at' => $startedAt,
            'status' => 'running',
            'trigger' => $trigger,
            'triggered_by' => $triggeredBy,
        ]);

        $status = 'failed';
        $output = '';

        try {
            $token = (string) config((string) $job->token_config_key, '');
            $request = Request::create($job->endpoint, 'GET', ['token' => $token]);

            $controller = app($job->handler);

            // Dispatch through the container so any additional typed dependencies
            // on `__invoke` (e.g. `AutoCloseResolvedTicketsService $service`) are
            // resolved via method injection. Binding `request` by name guarantees
            // the controllers see OUR synthetic Request with the cron token
            // (queue worker or admin manual run), not the inbound HTTP request.
            $response = app()->call($controller, ['request' => $request]);

            if ($response instanceof SymfonyResponse) {
                $code = $response->getStatusCode();
                $status = ($code >= 200 && $code < 400) ? 'success' : 'failed';
                $output = "HTTP {$code}\n".$this->responseBody($response);
            } else {
                $status = 'success';
                $output = is_string($response) ? $response : (string) json_encode($response);
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $output = $e::class.': '.$e->getMessage()."\n\n".$e->getTraceAsString();
            Log::error('ScheduledJobRunner failed', [
                'job_key' => $job->key,
                'error' => $e->getMessage(),
            ]);
        }

        $finishedAt = Carbon::now();
        $duration = round(microtime(true) - $start, 3);

        $run->update([
            'finished_at' => $finishedAt,
            'duration' => $duration,
            'status' => $status,
            'output' => $this->truncateOutput($output),
        ]);

        $job->update([
            'last_run_at' => $startedAt,
            'last_run_status' => $status,
            'last_run_duration' => $duration,
        ]);

        return $run->fresh();
    }

    private function responseBody(SymfonyResponse $response): string
    {
        $content = $response->getContent();
        if (! is_string($content)) {
            return '';
        }

        return $content;
    }

    private function truncateOutput(string $output): string
    {
        if (strlen($output) <= self::MAX_OUTPUT_LENGTH) {
            return $output;
        }

        return substr($output, 0, self::MAX_OUTPUT_LENGTH)
            ."\n\n... [truncated; original length was ".strlen($output).' characters]';
    }
}
