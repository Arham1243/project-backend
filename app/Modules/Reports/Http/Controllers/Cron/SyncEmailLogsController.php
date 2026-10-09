<?php

namespace App\Modules\Reports\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\MailgunEmailLogSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SyncEmailLogsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        $expected = (string) config('services.mailgun_email_log_sync.token', '');
        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(404);
        }

        $limit = (int) $request->query('limit', 400);
        $result = MailgunEmailLogSyncService::syncDueLogs($limit);

        return response(
            'OK processed='.$result['processed'].' updated='.$result['updated'].' errors='.$result['errors'],
            200
        );
    }
}
