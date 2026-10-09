<?php

namespace App\Modules\Administration\Services;

use App\Modules\Administration\Models\Admin\AdminEmailTemplate;
use App\Modules\Administration\Models\User;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;

class AdminAuthMailer
{
    public function __construct(
        private AdminEmailTemplateRenderer $renderer
    ) {}

    /**
     * @param  array<string, string|int>  $placeholders
     */
    public function send(string $slug, string $to, array $placeholders, int|string|null $userId = null): bool
    {
        $companyId = null;
        if ($userId !== null) {
            $companyId = User::withoutGlobalScopes()->find($userId)?->company_id;
        }

        $templateQuery = AdminEmailTemplate::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('status', true);

        if ($companyId) {
            $templateQuery->where('company_id', $companyId);
        }

        $row = $templateQuery->first();

        if ($row === null) {
            return false;
        }

        $normalized = [];
        foreach ($placeholders as $key => $value) {
            $normalized[(string) $key] = (string) $value;
        }
        if (! isset($normalized['year'])) {
            $normalized['year'] = now()->format('Y');
        }

        $built = $this->renderer->buildAuthEmail($slug, [], $normalized);

        try {
            $result = MailService::sendEmail([
                'company' => MailService::systemMailgunCompany(),
                'to' => $to,
                'subject' => $built['subject'],
                'html' => $built['html'],
                'template' => $row,
                'user_id' => $userId,
                'entity_type' => MailService::ENTITY_AUTH,
            ]);

            if (! $result['success']) {
                Log::error("Admin auth email ({$slug}) failed for {$to}: ".$result['message']);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("Admin auth email ({$slug}) failed for {$to}: ".$e->getMessage());

            return false;
        }
    }
}
