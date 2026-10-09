<?php

$cronApiTokenRaw = env('CRON_API_TOKEN');
$sharedCronApiToken =
    is_string($cronApiTokenRaw) && trim($cronApiTokenRaw) !== ''
        ? $cronApiTokenRaw
        : null;

return [

    'mailgun' => [
        'api_key' => env('MAILGUN_API_KEY'),
        'domain' => env('MAILGUN_DOMAIN'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'https://api.mailgun.net'),
    ],

    'mailgun_email_log_sync' => [
        'token' => $sharedCronApiToken,
    ],

    'healthcheck' => [
        'url' => env('HEALTHCHECK_URL'),
    ],

];
