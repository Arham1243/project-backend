<?php

use App\Modules\Core\Models\Company;

if (! function_exists('email_template_is_date_key')) {
    function email_template_is_date_key(string $key): bool
    {
        if (in_array($key, ['date', 'created_at', 'updated_at', 'paid_at', 'returned_at', 'approved_at'], true)) {
            return true;
        }

        return str_ends_with($key, '_date');
    }
}

if (! function_exists('email_company_from_data')) {
    /**
     * Resolve the tenant company for email rendering (date/amount formatting).
     *
     * @param  array<string, mixed>  $data
     */
    function email_company_from_data(array $data): ?Company
    {
        if (($data['company'] ?? null) instanceof Company) {
            return $data['company'];
        }

        $companyId = $data['company_id'] ?? ($data['template_data']->company_id ?? null);
        if ($companyId) {
            return Company::query()->find($companyId);
        }

        $user = auth()->user();
        if ($user && method_exists($user, 'company')) {
            $user->loadMissing('company');

            return $user->company;
        }

        return null;
    }
}

if (! function_exists('format_email_template_replacements')) {
    /**
     * @param  array<string, mixed>  $replacements
     * @return array<string, mixed>
     */
    function format_email_template_replacements(array $replacements, $company = null): array
    {
        foreach ($replacements as $key => $value) {
            if (! is_string($key) || is_array($value) || $value === null || $value === '') {
                continue;
            }

            if (email_template_is_date_key($key)) {
                $replacements[$key] = format_date($value, null, $company);
            }
        }

        return $replacements;
    }
}

if (! function_exists('render_template')) {
    function render_template(?string $template, array $replacements = [], $company = null): string
    {
        if ($template === null) {
            return '';
        }

        $replacements = format_email_template_replacements($replacements, $company);

        foreach ($replacements as $key => $value) {
            if (is_array($value)) {
                $value = '<ul><li>'.implode('</li><li>', array_map('htmlspecialchars', $value)).'</li></ul>';
            } else {
                $value = htmlspecialchars($value);
            }

            $template = str_replace('{{ '.$key.' }}', $value, $template);
        }

        return nl2br($template);
    }
}

if (! function_exists('php_date_format_for_company_date_setting')) {
    function php_date_format_for_company_date_setting(?string $companyFormat): string
    {
        $default = 'M d, Y';
        if ($companyFormat === null || $companyFormat === '') {
            return $default;
        }

        $map = [
            'mm/dd/yy' => 'm/d/Y',
            'dd/mm/yy' => 'd/m/Y',
            'yy-mm-dd' => 'Y-m-d',
        ];

        return $map[$companyFormat] ?? $default;
    }
}

if (! function_exists('format_date')) {
    /**
     * Format a date/time for display, converting to the resolved company (or app default) timezone when applicable.
     *
     * @param  mixed  $date
     * @param  string|null  $format  Explicit PHP date format; when null or empty, uses company (third arg) or authenticated user's company.
     * @param  Company|null  $company  When set and $format is empty, uses this company's date_format. Also preferred for resolving display timezone.
     */
    function format_date($date, $format = null, $company = null)
    {
        if ($date === null || $date === '') {
            return '';
        }

        if (! $format) {
            if ($company instanceof Company) {
                $format = php_date_format_for_company_date_setting($company->date_format);
            } else {
                $format = get_company_date_format();
            }
        }

        $displayTz = (string) config('app.timezone');
        $tzCompany = null;
        if ($company instanceof Company) {
            $tzCompany = $company;
        } else {
            $user = auth()->user();
            if ($user && method_exists($user, 'company')) {
                $user->loadMissing('company');
                $tzCompany = $user->company;
            }
        }
        if ($tzCompany && filled($tzCompany->timezone)) {
            try {
                new \DateTimeZone((string) $tzCompany->timezone);
                $displayTz = (string) $tzCompany->timezone;
            } catch (\Throwable) {
                // keep app timezone
            }
        }

        try {
            if ($date instanceof \Carbon\CarbonInterface) {
                $dt = $date->copy()->setTimezone($displayTz);
            } elseif ($date instanceof \DateTimeInterface) {
                $dt = \Carbon\Carbon::parse($date)->setTimezone($displayTz);
            } elseif (is_string($date)) {
                $s = trim($date);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                    $dt = \Carbon\Carbon::createFromFormat('!Y-m-d', $s, $displayTz)->startOfDay();
                } else {
                    $dt = \Carbon\Carbon::parse($s, (string) config('app.timezone'))->setTimezone($displayTz);
                }
            } else {
                $dt = \Carbon\Carbon::parse($date, (string) config('app.timezone'))->setTimezone($displayTz);
            }

            return $dt->format($format);
        } catch (\Throwable) {
            return is_scalar($date) ? (string) $date : '';
        }
    }
}

if (! function_exists('get_company_date_format')) {
    function get_company_date_format(): string
    {
        try {
            $user = auth()->user();
            if (! $user || ! method_exists($user, 'company')) {
                return php_date_format_for_company_date_setting(null);
            }

            $user->loadMissing('company');
            if (! $user->company) {
                return php_date_format_for_company_date_setting(null);
            }

            return php_date_format_for_company_date_setting($user->company->date_format);
        } catch (\Exception $e) {
            return php_date_format_for_company_date_setting(null);
        }
    }
}

if (! function_exists('pdf_export_cell_is_right_aligned')) {
    function pdf_export_cell_is_right_aligned(string $key): bool
    {
        return false;
    }
}

if (! function_exists('format_duration_seconds')) {
    function format_duration_seconds(mixed $totalSeconds): string
    {
        if ($totalSeconds === null || $totalSeconds === '') {
            return '';
        }
        if (! is_numeric($totalSeconds)) {
            return '';
        }
        $total = max(0, (int) $totalSeconds);
        if ($total === 0) {
            return '0m';
        }
        $days = intdiv($total, 86400);
        $hours = intdiv($total % 86400, 3600);
        $minutes = intdiv($total % 3600, 60);
        $parts = [];
        if ($days > 0) {
            $parts[] = $days.'d';
        }
        if ($hours > 0) {
            $parts[] = $hours.'h';
        }
        if ($minutes > 0 || $parts === []) {
            $parts[] = $minutes.'m';
        }

        return implode(' ', $parts);
    }
}
