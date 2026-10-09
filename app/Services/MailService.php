<?php

namespace App\Services;

use App\Modules\Reports\Models\EmailLog;
use Illuminate\Support\Facades\Log;
use Mailgun\Exception\HttpClientException;
use Mailgun\Mailgun;

class MailService
{
    /** Device OTP, password reset, login mail */
    public const ENTITY_AUTH = 'auth';

    public const ENTITY_SYSTEM = 'system';

    public const ENTITY_USER = 'user';

    /**
     * Mailgun user-variable name for the internal actor id (maps from sendEmail() user_id param).
     */
    public const MAILGUN_VAR_ACTOR_REF = 'sc_ref';

    /**
     * Env-backed Mailgun context for admin-portal sends (not per-tenant credentials).
     */
    public static function systemMailgunCompany(): object
    {
        return self::envMailgunCompany();
    }

    /**
     * Admin portal: always uses env Mailgun credentials; {@see $company} is ignored.
     */
    public static function tenantOrSystemMailgunCompany(?object $company): object
    {
        return self::envMailgunCompany();
    }

    /**
     * @return array{api_key: string, domain: string, endpoint: string}
     */
    private static function mailgunCredentialsFromConfig(): array
    {
        return [
            'api_key' => trim((string) config('services.mailgun.api_key', '')),
            'domain' => trim((string) config('services.mailgun.domain', '')),
            'endpoint' => rtrim(trim((string) config('services.mailgun.endpoint', 'https://api.mailgun.net')), '/'),
        ];
    }

    private static function envMailgunCompany(): object
    {
        $creds = self::mailgunCredentialsFromConfig();

        return (object) [
            'id' => 'admin',
            'mailgun_api_key' => $creds['api_key'],
            'mailgun_domain' => $creds['domain'],
        ];
    }

    private static function resolveMailTemplate(mixed $template): object
    {
        if (is_object($template)) {
            return $template;
        }

        return (object) [
            'from' => 'noreply',
            'display_name' => config('mail.from.name', 'AdaptiveQMS'),
            'reply_to_email' => config('mail.from.address', ''),
            'reply_to_name' => config('mail.from.name', ''),
            'bcc_recipients' => null,
            'slug' => null,
        ];
    }

    /**
     * Send email using Mailgun API (admin portal: credentials from env/config only).
     *
     * @param  array{to: string, subject: string, html: string, company?: object, template?: object, cc?: string|array<int, string>|null, user_id?: int|string|null, entity_type?: string|null, entity_id?: int|string|null, mailgun_tags?: array<int, string>, attachments?: array, mailgun_variables?: array<string, string>}  $params
     * @return array{success: bool, message: string, status_code: int|null, email_log_id: int|null}
     */
    public static function sendEmail(array $params): array
    {
        foreach (['to', 'subject', 'html'] as $field) {
            if (! array_key_exists($field, $params) || $params[$field] === '' || $params[$field] === null) {
                return [
                    'success' => false,
                    'message' => "Missing required parameter: {$field}",
                    'status_code' => null,
                    'email_log_id' => null,
                ];
            }
        }

        $to = $params['to'];
        $subject = $params['subject'];
        $htmlContent = $params['html'];
        $attachments = $params['attachments'] ?? [];
        $userId = $params['user_id'] ?? null;
        $template = self::resolveMailTemplate($params['template'] ?? null);

        $creds = self::mailgunCredentialsFromConfig();
        $apiKey = $creds['api_key'];
        $mailgunDomain = $creds['domain'];
        $endpoint = $creds['endpoint'];

        if ($apiKey === '' || $mailgunDomain === '') {
            Log::error('Mailgun is not configured (MAILGUN_API_KEY / MAILGUN_DOMAIN).');

            return [
                'success' => false,
                'message' => 'Email service not configured.',
                'status_code' => null,
                'email_log_id' => null,
            ];
        }

        try {
            $mg = Mailgun::create($apiKey, $endpoint);

            $localPart = trim((string) ($template->from ?? ''));
            if ($localPart === '') {
                $localPart = 'noreply';
            }
            $fromAddress = $localPart.'@'.$mailgunDomain;

            $fromName = trim((string) ($template->display_name ?? ''));
            if ($fromName === '') {
                $fromName = (string) config('mail.from.name', 'AdaptiveQMS');
            }
            $from = '"'.addcslashes($fromName, "\"\\").'" <'.$fromAddress.'>';

            $tags = ['adaptiveqms'];
            if (! empty($template->slug)) {
                $tags[] = MailgunReportingService::templateTag((string) $template->slug);
            }
            if (! empty($params['mailgun_tags']) && is_array($params['mailgun_tags'])) {
                foreach ($params['mailgun_tags'] as $extra) {
                    if (is_string($extra) && $extra !== '') {
                        $tags[] = $extra;
                    }
                }
            }
            $tags = array_values(array_unique(array_filter(array_map(
                static fn ($tag) => self::sanitizeMailgunTag((string) $tag),
                $tags
            ))));

            $replyEmail = trim((string) ($template->reply_to_email ?? ''));
            if ($replyEmail === '') {
                $replyEmail = trim((string) config('mail.from.address', ''));
            }
            $replyName = trim((string) ($template->reply_to_name ?? ''));
            if ($replyName === '') {
                $replyName = trim((string) config('mail.from.name', ''));
            }
            $replyToHeader = $replyEmail !== ''
                ? ($replyName !== ''
                    ? '"'.addcslashes($replyName, "\"\\").'" <'.$replyEmail.'>'
                    : $replyEmail)
                : '';

            $messageParams = [
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'html' => $htmlContent,
                'o:tag' => $tags,
            ];

            if ($replyToHeader !== '') {
                $messageParams['h:Reply-To'] = $replyToHeader;
            }

            self::applyMailgunUserVariables($messageParams, $params);

            $ccHeader = self::normalizeAddressList($params['cc'] ?? null);
            if ($ccHeader !== null) {
                $messageParams['cc'] = $ccHeader;
            }

            $bccHeader = self::buildBccHeader(
                $template->bcc_recipients ?? null,
                $to,
                $params['cc'] ?? null
            );
            if ($bccHeader !== null) {
                $messageParams['bcc'] = $bccHeader;
            }

            if (! empty($attachments)) {
                $messageParams['attachment'] = [];
                foreach ($attachments as $attachment) {
                    if (is_array($attachment) && isset($attachment['path'])) {
                        $messageParams['attachment'][] = [
                            'filePath' => $attachment['path'],
                            'filename' => $attachment['filename'] ?? basename($attachment['path']),
                        ];
                    } elseif (is_string($attachment)) {
                        $messageParams['attachment'][] = ['filePath' => $attachment];
                    }
                }
            }

            $sendResponse = $mg->messages()->send($mailgunDomain, $messageParams);
            $emailLogId = self::persistEmailLog(array_merge(
                self::emailLogBaseline($params, $to, $subject, $template),
                [
                    'mailgun_message_id' => $sendResponse->getId() ?: null,
                    'mailgun_send_message' => $sendResponse->getMessage() ?: null,
                    'status' => 'queued',
                    'failure_reason' => null,
                    'mailgun_severity' => null,
                ]
            ));

            return [
                'success' => true,
                'message' => 'Email sent successfully.',
                'status_code' => 200,
                'email_log_id' => $emailLogId,
            ];
        } catch (HttpClientException $e) {
            $statusCode = $e->getCode();

            if ($statusCode == 401) {
                Log::error('Invalid Mailgun API key (MAILGUN_API_KEY).');
                $emailLogId = self::persistEmailLog(array_merge(
                    self::emailLogBaseline($params, $to, $subject, $template),
                    [
                        'mailgun_message_id' => null,
                        'mailgun_send_message' => null,
                        'status' => 'send_failed',
                        'failure_reason' => 'Email delivery failed. Invalid email settings.',
                        'mailgun_severity' => null,
                    ]
                ));

                return [
                    'success' => false,
                    'message' => 'Email delivery failed. Invalid email settings.',
                    'status_code' => 401,
                    'email_log_id' => $emailLogId,
                ];
            }

            if ($statusCode == 403) {
                Log::error('Mailgun domain not verified (MAILGUN_DOMAIN).');
                $emailLogId = self::persistEmailLog(array_merge(
                    self::emailLogBaseline($params, $to, $subject, $template),
                    [
                        'mailgun_message_id' => null,
                        'mailgun_send_message' => null,
                        'status' => 'send_failed',
                        'failure_reason' => 'Email domain not verified.',
                        'mailgun_severity' => null,
                    ]
                ));

                return [
                    'success' => false,
                    'message' => 'Email domain not verified.',
                    'status_code' => 403,
                    'email_log_id' => $emailLogId,
                ];
            }

            if ($statusCode == 429) {
                $emailLogId = self::persistEmailLog(array_merge(
                    self::emailLogBaseline($params, $to, $subject, $template),
                    [
                        'mailgun_message_id' => null,
                        'mailgun_send_message' => null,
                        'status' => 'send_failed',
                        'failure_reason' => 'Daily email limit reached.',
                        'mailgun_severity' => null,
                    ]
                ));

                return [
                    'success' => false,
                    'message' => 'Daily email limit reached. Try again tomorrow.',
                    'status_code' => 429,
                    'email_log_id' => $emailLogId,
                ];
            }

            $userContext = $userId ? " for user {$userId}" : '';
            Log::error("Email sending failed{$userContext}: ".$e->getMessage());
            $emailLogId = self::persistEmailLog(array_merge(
                self::emailLogBaseline($params, $to, $subject, $template),
                [
                    'mailgun_message_id' => null,
                    'mailgun_send_message' => null,
                    'status' => 'send_failed',
                    'failure_reason' => 'Email sending failed.'.($statusCode ? " (HTTP {$statusCode})" : ''),
                    'mailgun_severity' => null,
                ]
            ));

            return [
                'success' => false,
                'message' => 'Email sending failed.',
                'status_code' => $statusCode ?: null,
                'email_log_id' => $emailLogId,
            ];
        } catch (\Throwable $e) {
            $userContext = $userId ? " for user {$userId}" : '';
            Log::error("Unexpected email error{$userContext}: ".$e->getMessage(), [
                'exception' => $e::class,
            ]);

            $message = 'An unexpected error occurred while sending email.';
            if (config('app.debug')) {
                $detail = trim($e->getMessage());
                if ($detail !== '') {
                    $message = $detail;
                }
            }

            $emailLogId = self::persistEmailLog(array_merge(
                self::emailLogBaseline($params, $to, $subject, $template),
                [
                    'mailgun_message_id' => null,
                    'mailgun_send_message' => null,
                    'status' => 'send_failed',
                    'failure_reason' => 'Unexpected error while sending email.',
                    'mailgun_severity' => null,
                ]
            ));

            return [
                'success' => false,
                'message' => $message,
                'status_code' => 500,
                'email_log_id' => $emailLogId,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private static function emailLogBaseline(array $params, string $to, string $subject, object $template): array
    {
        $entityType = null;
        if (! empty($params['entity_type']) && is_string($params['entity_type'])) {
            $entityType = self::normalizeEntityToken($params['entity_type']);
        }

        $entityId = null;
        if (array_key_exists('entity_id', $params) && $params['entity_id'] !== null && $params['entity_id'] !== '') {
            $entityId = substr((string) $params['entity_id'], 0, 64);
        }

        $slug = null;
        if (! empty($template->slug)) {
            $slug = substr((string) $template->slug, 0, 255);
        }

        $adminEmailTemplateId = null;
        if (isset($template->id) && is_numeric($template->id)) {
            $adminEmailTemplateId = (int) $template->id;
        }

        return [
            'company_id' => self::resolveCompanyIdForEmailLog($params),
            'admin_id' => self::resolveAdminIdForEmailLog($params),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'template_slug' => $slug,
            'admin_email_template_id' => $adminEmailTemplateId,
            'recipient' => $to,
            'subject' => $subject !== '' ? $subject : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function resolveCompanyIdForEmailLog(array $params): ?int
    {
        $uid = self::resolveAdminIdForEmailLog($params);
        if ($uid === null) {
            $user = auth()->user();
            if ($user && isset($user->company_id)) {
                return (int) $user->company_id;
            }

            return null;
        }

        $admin = \App\Modules\Administration\Models\User::withoutGlobalScopes()->find($uid);

        return $admin?->company_id ? (int) $admin->company_id : null;
    }

    private static function resolveAdminIdForEmailLog(array $params): ?int
    {
        $entityType = is_string($params['entity_type'] ?? null)
            ? (string) $params['entity_type']
            : '';

        $uid = $params['user_id'] ?? null;
        if (is_int($uid)) {
            return $uid;
        }
        if (is_string($uid) && ctype_digit($uid)) {
            return (int) $uid;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function persistEmailLog(array $attributes): ?int
    {
        if (empty($attributes['admin_email_template_id']) && empty($attributes['template_slug'])) {
            return null;
        }

        try {
            $row = EmailLog::withoutGlobalScopes()->create($attributes);

            return (int) $row->id;
        } catch (\Throwable $e) {
            Log::warning('email_log_write_failed', [
                'message' => $e->getMessage(),
                'template_slug' => $attributes['template_slug'] ?? null,
                'subject' => isset($attributes['subject'])
                    ? substr((string) $attributes['subject'], 0, 200)
                    : null,
                'recipient_length' => isset($attributes['recipient'])
                    ? strlen((string) $attributes['recipient'])
                    : null,
            ]);

            return null;
        }
    }

    /**
     * Mailgun tags allow only ASCII letters, digits, underscore, and hyphen.
     */
    private static function sanitizeMailgunTag(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return 'tag';
        }
        $tag = preg_replace('/[^a-zA-Z0-9_-]/', '-', $tag) ?? $tag;
        $tag = preg_replace('/-+/', '-', $tag) ?? $tag;
        $tag = trim($tag, '-');
        if ($tag === '') {
            return 'tag';
        }

        return strlen($tag) > 128 ? substr($tag, 0, 128) : $tag;
    }

    private static function mailgunJsonVariableValue(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function applyMailgunUserVariables(array &$messageParams, array $params): void
    {
        if (array_key_exists('user_id', $params) && $params['user_id'] !== null && $params['user_id'] !== '') {
            $messageParams['v:'.self::MAILGUN_VAR_ACTOR_REF] = self::mailgunJsonVariableValue((string) $params['user_id']);
        }

        if (! empty($params['entity_type']) && is_string($params['entity_type'])) {
            $t = self::normalizeEntityToken($params['entity_type']);
            if ($t !== null) {
                $messageParams['v:entity_type'] = self::mailgunJsonVariableValue($t);
            }
        }

        if (array_key_exists('entity_id', $params) && $params['entity_id'] !== null && $params['entity_id'] !== '') {
            $messageParams['v:entity_id'] = self::mailgunJsonVariableValue((string) $params['entity_id']);
        }

        if (empty($params['mailgun_variables']) || ! is_array($params['mailgun_variables'])) {
            return;
        }

        foreach ($params['mailgun_variables'] as $name => $value) {
            if (! is_string($name) || $name === '' || ! is_string($value)) {
                continue;
            }
            $k = ltrim($name, 'v');
            $k = ltrim($k, ':');
            if ($k === '' || str_contains($k, ':')) {
                continue;
            }
            $n = self::normalizeEntityToken($k) ?? $k;
            if (strlen($n) > 64) {
                $n = substr($n, 0, 64);
            }
            if (strlen($value) > 1024) {
                $value = substr($value, 0, 1024);
            }
            $messageParams['v:'.$n] = self::mailgunJsonVariableValue($value);
        }
    }

    private static function extractEmailAddress(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/<([^>]+)>\s*$/', $value, $m) === 1) {
            return trim($m[1]);
        }

        return $value;
    }

    /**
     * @return array<string, true>
     */
    private static function primaryRecipientKeys(string $to, mixed $cc): array
    {
        $keys = [];
        $toAddr = self::extractEmailAddress($to);
        if ($toAddr !== null && $toAddr !== '' && filter_var($toAddr, FILTER_VALIDATE_EMAIL)) {
            $keys[mb_strtolower($toAddr)] = true;
        }
        $ccNorm = self::normalizeAddressList($cc);
        if ($ccNorm === null) {
            return $keys;
        }
        foreach (preg_split('/,\s*/', $ccNorm) as $part) {
            $e = self::extractEmailAddress(trim((string) $part));
            if ($e !== null && $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $keys[mb_strtolower($e)] = true;
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private static function normalizeBccRecipientList(mixed $bccRecipients): array
    {
        if ($bccRecipients === null || $bccRecipients === '' || $bccRecipients === []) {
            return [];
        }
        $items = is_array($bccRecipients) ? $bccRecipients : preg_split('/[,;]+/', (string) $bccRecipients);
        $seen = [];
        $out = [];
        foreach ($items as $entry) {
            $raw = trim((string) $entry);
            if ($raw === '') {
                continue;
            }
            $email = self::extractEmailAddress($raw);
            if ($email === null || $email === '') {
                continue;
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $key = mb_strtolower($email);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $email;
        }

        return $out;
    }

    private static function buildBccHeader(mixed $bccRecipients, string $to, mixed $cc): ?string
    {
        $list = self::normalizeBccRecipientList($bccRecipients);
        if ($list === []) {
            return null;
        }
        $skip = self::primaryRecipientKeys($to, $cc);
        $filtered = [];
        foreach ($list as $email) {
            if (! isset($skip[mb_strtolower($email)])) {
                $filtered[] = $email;
            }
        }
        if ($filtered === []) {
            return null;
        }

        return implode(', ', $filtered);
    }

    private static function normalizeAddressList(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $items = is_array($value) ? $value : preg_split('/[,;]+/', (string) $value);
        $seen = [];
        $out = [];
        foreach ((array) $items as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }
            $key = mb_strtolower($entry);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $entry;
        }

        return $out === [] ? null : implode(', ', $out);
    }

    private static function normalizeEntityToken(string $value): ?string
    {
        $t = trim($value);
        if ($t === '' || strlen($t) > 64) {
            return null;
        }
        if (preg_match('/^[a-zA-Z0-9_\-]+$/', $t) !== 1) {
            return null;
        }

        return strtolower($t);
    }
}
