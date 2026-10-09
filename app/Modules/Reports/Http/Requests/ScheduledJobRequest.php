<?php

namespace App\Modules\Reports\Http\Requests;

use Closure;
use Cron\CronExpression;
use Orion\Http\Requests\Request;

class ScheduledJobRequest extends Request
{
    public function commonRules(): array
    {
        return [];
    }

    /**
     * Scheduled jobs are seeded and never created from the UI.
     */
    public function storeRules(): array
    {
        return [];
    }

    /**
     * Super admins can change the cron expression and optionally pause / resume
     * a job by toggling `enabled`. All other columns are read-only from the UI.
     */
    public function updateRules(): array
    {
        return [
            'frequency' => [
                'required',
                'string',
                'max:191',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! CronExpression::isValidExpression($value)) {
                        $fail('The frequency must be a valid cron expression (e.g. "0 * * * *").');
                    }
                },
            ],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
