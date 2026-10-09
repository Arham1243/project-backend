<?php

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Models\EmailLog;
use App\Services\MailgunReportingService;
use Illuminate\Support\Facades\Log;
use Mailgun\Exception\HttpClientException as MailgunHttpException;
use Mailgun\Mailgun;
use Mailgun\Model\Event\Event as MailgunEvent;

class MailgunEmailLogSyncService
{
    /**
     * @return array{processed: int, updated: int, errors: int}
     */
    public static function syncDueLogs(int $limit = 400): array
    {
        $out = ['processed' => 0, 'updated' => 0, 'errors' => 0];

        $logs = EmailLog::query()
            ->whereNotNull('mailgun_message_id')
            ->whereIn('status', ['queued', 'accepted', 'temporary_failed', 'delivered'])
            ->orderBy('id')
            ->limit(max(1, min($limit, 1000)))
            ->get();

        foreach ($logs as $log) {
            $out['processed']++;
            try {
                if (self::syncOne($log)) {
                    $out['updated']++;
                }
            } catch (\Throwable $e) {
                $out['errors']++;
                Log::warning('email_log_sync_failed', [
                    'log_id' => $log->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $out;
    }

    public static function syncOne(EmailLog $log): bool
    {
        $messageId = trim((string) $log->mailgun_message_id);
        if ($messageId === '') {
            return false;
        }

        $creds = MailgunReportingService::credentialsFromConfig();
        $mg = Mailgun::create($creds['api_key'], $creds['endpoint']);
        $paramId = trim($messageId, '<>');

        try {
            $response = $mg->events()->get($creds['domain'], [
                'message-id' => $paramId,
                'limit' => 100,
            ]);
        } catch (MailgunHttpException $e) {
            throw new \RuntimeException(
                'Mailgun events request failed: '.preg_replace('/\s+/u', ' ', trim($e->getMessage())),
                0,
                $e
            );
        }

        $items = $response->getItems();
        if ($items === []) {
            $log->forceFill([
                'last_synced_at' => now(),
            ])->save();

            return true;
        }

        $summary = self::summarizeEvents($items);

        $log->forceFill([
            'status' => $summary['status'],
            'failure_reason' => $summary['failure_reason'],
            'mailgun_severity' => $summary['mailgun_severity'],
            'mailgun_last_event_ts' => $summary['mailgun_last_event_ts'],
            'mailgun_events_snapshot' => $summary['snapshot'],
            'last_synced_at' => now(),
        ])->save();

        return true;
    }

    /**
     * @param  array<int, MailgunEvent>  $events
     * @return array{status: string, failure_reason: ?string, mailgun_severity: ?string, mailgun_last_event_ts: ?int, snapshot: array<string, mixed>}
     */
    private static function summarizeEvents(array $events): array
    {
        $sorted = $events;
        usort($sorted, fn (MailgunEvent $a, MailgunEvent $b) => $a->getTimestamp() <=> $b->getTimestamp());

        $complained = false;
        $permanentFail = null;
        $temporaryFail = null;
        $delivered = false;
        $opened = false;
        $accepted = false;
        $lastTs = null;
        $lastType = null;

        foreach ($sorted as $e) {
            $lastTs = $e->getTimestamp();
            $lastType = $e->getEvent();
            $ev = $e->getEvent();
            if ($ev === 'complained') {
                $complained = true;
            }
            if ($ev === 'failed') {
                $sev = $e->getSeverity();
                $detail = self::failureDetailFromEvent($e);
                if ($sev === 'permanent') {
                    $permanentFail = $detail;
                } else {
                    $temporaryFail = $detail;
                }
            }
            if ($ev === 'delivered') {
                $delivered = true;
            }
            if ($ev === 'opened') {
                $opened = true;
            }
            if ($ev === 'accepted') {
                $accepted = true;
            }
        }

        $status = 'queued';
        $failureReason = null;
        $severity = null;

        if ($complained) {
            $status = 'complained';
            $failureReason = 'Recipient marked message as spam.';
        } elseif ($permanentFail !== null) {
            $status = 'failed';
            $failureReason = $permanentFail;
            $severity = 'permanent';
        } elseif ($opened) {
            $status = 'opened';
        } elseif ($delivered) {
            $status = 'delivered';
        } elseif ($temporaryFail !== null) {
            $status = 'temporary_failed';
            $failureReason = $temporaryFail;
            $severity = 'temporary';
        } elseif ($accepted) {
            $status = 'accepted';
        } elseif ($lastType !== null) {
            $status = $lastType;
        }

        $snapshot = [
            'last_event' => $lastType,
            'counts' => self::countEventsByType($sorted),
        ];

        return [
            'status' => $status,
            'failure_reason' => $failureReason,
            'mailgun_severity' => $severity,
            'mailgun_last_event_ts' => $lastTs,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @param  array<int, MailgunEvent>  $events
     * @return array<string, int>
     */
    private static function countEventsByType(array $events): array
    {
        $counts = [];
        foreach ($events as $e) {
            $k = $e->getEvent();
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }

        return $counts;
    }

    private static function failureDetailFromEvent(MailgunEvent $e): string
    {
        $parts = [];
        $r = $e->getReason();
        if ($r !== '') {
            $parts[] = $r;
        }
        $ds = $e->getDeliveryStatus();
        if ($ds !== []) {
            foreach (['message', 'description', 'error'] as $k) {
                if (! empty($ds[$k]) && is_string($ds[$k])) {
                    $parts[] = $ds[$k];
                    break;
                }
            }
            if (! empty($ds['code'])) {
                $parts[] = 'Code: '.(is_scalar($ds['code']) ? (string) $ds['code'] : json_encode($ds['code']));
            }
        }
        $detail = implode(' - ', array_values(array_unique(array_filter($parts))));

        return $detail !== '' ? $detail : 'Delivery failed.';
    }
}
