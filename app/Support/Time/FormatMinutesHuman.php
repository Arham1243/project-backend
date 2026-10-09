<?php

namespace App\Support\Time;

/**
 * Human-readable duration for escalation fields. Mirrors {@see qms-admin-frontend formatMinutesHuman.js}.
 */
final class FormatMinutesHuman
{
    public static function format(int $minutes): ?string
    {
        if ($minutes < 1) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        $hourLabel = $hours === 1 ? 'Hour' : 'Hours';

        if ($mins === 0) {
            return "{$hours} {$hourLabel}";
        }

        $minuteLabel = $mins === 1 ? 'minute' : 'minutes';

        if ($hours === 0) {
            return "{$mins} {$minuteLabel}";
        }

        return "{$hours} {$hourLabel} {$mins} {$minuteLabel}";
    }
}
