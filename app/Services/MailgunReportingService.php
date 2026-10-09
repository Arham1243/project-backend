<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Mailgun\Mailgun;
use Mailgun\Model\Stats\TotalResponse;

class MailgunReportingService
{
    public const TEMPLATE_TAG_PREFIX = 'adaptiveqms-template:';

    public static function templateTag(string $slug): string
    {
        return self::TEMPLATE_TAG_PREFIX.ltrim($slug, ':');
    }

    /**
     * @return array{api_key: string, domain: string, endpoint: string}
     */
    public static function credentialsFromConfig(): array
    {
        $apiKey = trim((string) config('services.mailgun.api_key', ''));
        $domain = trim((string) config('services.mailgun.domain', ''));
        $endpoint = self::mailgunBaseUrl();

        if ($apiKey === '' || $domain === '') {
            throw new \RuntimeException('Mailgun is not configured (MAILGUN_API_KEY / MAILGUN_DOMAIN).');
        }

        return [
            'api_key' => $apiKey,
            'domain' => $domain,
            'endpoint' => $endpoint,
        ];
    }

    public static function mailgunBaseUrl(): string
    {
        return rtrim(trim((string) config('services.mailgun.endpoint', 'https://api.mailgun.net')), '/');
    }

    /**
     * @return array{accepted: int, delivered: int, failed: int, complained: int}
     */
    public static function domainStatsTotalsLastDays(int $days = 7): array
    {
        $creds = self::credentialsFromConfig();
        $mg = Mailgun::create($creds['api_key'], $creds['endpoint']);
        $duration = max(1, min($days, 90)).'d';

        $out = [
            'accepted' => 0,
            'delivered' => 0,
            'failed' => 0,
            'complained' => 0,
        ];

        foreach (array_keys($out) as $eventName) {
            try {
                /** @var TotalResponse $totals */
                $totals = $mg->stats()->total($creds['domain'], [
                    'event' => $eventName,
                    'duration' => $duration,
                ]);
            } catch (\Throwable $e) {
                Log::warning("Mailgun stats ({$eventName}) failed: ".$e->getMessage());

                continue;
            }
            foreach ($totals->getStats() as $row) {
                $bucket = match ($eventName) {
                    'accepted' => $row->getAccepted(),
                    'delivered' => $row->getDelivered(),
                    'failed' => $row->getFailed(),
                    'complained' => $row->getComplained(),
                    default => [],
                };
                $out[$eventName] += (int) ($bucket['total'] ?? 0);
            }
        }

        return $out;
    }
}
