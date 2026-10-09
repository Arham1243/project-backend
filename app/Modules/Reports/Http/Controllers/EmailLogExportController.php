<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ExportsTabularExcel;
use App\Http\Controllers\Concerns\ExportsTabularPdf;
use App\Modules\Reports\Models\EmailLog;
use App\Modules\Reports\Services\EmailLogListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailLogExportController extends Controller
{
    use ExportsTabularExcel;
    use ExportsTabularPdf;

    private const HEADER_MAP = [
        'created_at' => 'Date',
        'recipient' => 'Recipients',
        'subject' => 'Subject',
        'template_slug' => 'Template',
        'admin.name' => 'Email',
        'status' => 'Status',
        'failure_reason' => 'Detail',
        'mailgun_message_id' => 'Message ID',
    ];

    public function export(Request $request): StreamedResponse|Response|\Illuminate\Http\JsonResponse
    {
        $format = strtolower((string) $request->input('format', 'excel'));
        if (! in_array($format, ['excel', 'pdf'], true)) {
            return response()->json(['error' => 'Invalid format'], 400);
        }

        $columns = array_values(array_filter(
            $request->input('columns', []),
            fn ($col) => is_string($col) && $col !== ''
        ));
        if ($columns === []) {
            $columns = array_keys(self::HEADER_MAP);
        }

        $filters = $request->input('filters', []);
        $customFilters = $request->input('customFilters', []);
        $sort = $request->input('sort', []);

        $items = EmailLogListQuery::build($filters, $customFilters, $sort)->get();

        $data = $items->map(function (EmailLog $log) use ($columns) {
            return $this->rowForExport($log, $columns);
        })->all();

        if ($format === 'pdf') {
            return $this->streamPdfDownload(
                'Email Logs',
                $data,
                $columns,
                self::HEADER_MAP,
                'email-logs.pdf'
            );
        }

        $spreadsheet = $this->spreadsheetFromTabularRows($data, $columns, self::HEADER_MAP);

        return $this->streamXlsxDownload($spreadsheet, 'email-logs.xlsx');
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function rowForExport(EmailLog $log, array $columns): array
    {
        $row = [];
        foreach ($columns as $col) {
            $row[$col] = $this->exportValue($log, $col);
        }

        return $row;
    }

    private function exportValue(EmailLog $log, string $column): mixed
    {
        return match ($column) {
            'created_at' => $log->created_at ? format_date($log->created_at) : null,
            'recipient' => $log->recipient,
            'subject' => $log->subject,
            'template_slug' => $log->adminEmailTemplate?->name
                ?? ($log->template_slug ? Str::headline(str_replace(['-', '_'], ' ', $log->template_slug)) : null),
            'admin.name' => $log->admin?->email ?: $log->admin?->name,
            'status' => is_string($log->status) && $log->status !== ''
                ? ucwords(str_replace('_', ' ', $log->status))
                : $log->status,
            'failure_reason' => $log->failure_reason,
            'mailgun_message_id' => $this->formatMailgunMessageIdForExport($log->mailgun_message_id),
            default => Str::contains($column, '.')
                ? $this->nestedValue($log, $column)
                : ($log->{$column} ?? null),
        };
    }

    private function nestedValue(EmailLog $log, string $column): mixed
    {
        [$relation, $field] = explode('.', $column, 2);
        $related = $log->{$relation};

        return $related ? ($related->{$field} ?? null) : null;
    }

    private function formatMailgunMessageIdForExport(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $s = trim($raw, '<>');
        if (str_contains($s, '@')) {
            return explode('@', $s, 2)[0];
        }

        return $s;
    }
}
