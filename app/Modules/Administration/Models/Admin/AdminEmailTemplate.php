<?php

namespace App\Modules\Administration\Models\Admin;

use App\Enums\ActivityLogName;
use App\Traits\AuditsChanges;
use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class AdminEmailTemplate extends Model
{
    use AuditsChanges, BelongsToCompany, HasUuid;

    public const SLUG_LOGIN_OTP = 'login-otp';

    public const SLUG_RESET_PASSWORD = 'reset-password';

    public const SLUG_SETUP_PASSWORD = 'setup-password';

    public static function isAuthSlug(string $slug): bool
    {
        return in_array($slug, [
            self::SLUG_LOGIN_OTP,
            self::SLUG_RESET_PASSWORD,
            self::SLUG_SETUP_PASSWORD,
        ], true);
    }

    public static function defaultCtaLabelForSlug(string $slug): ?string
    {
        return match ($slug) {
            self::SLUG_RESET_PASSWORD => 'Password Reset',
            self::SLUG_SETUP_PASSWORD => 'Get Started',
            default => null,
        };
    }

    public const DEFAULT_COPYRIGHT_LINE = '© {{year}} AdaptiveQMS. All rights reserved.';

    public static function defaultSubjectTemplateForSlug(string $slug): string
    {
        return match ($slug) {
            self::SLUG_LOGIN_OTP => 'Verification Code',
            self::SLUG_RESET_PASSWORD => 'Password Reset',
            self::SLUG_SETUP_PASSWORD => 'Welcome - Set Up Your Password',
            default => 'Notification',
        };
    }

    /**
     * @return array{from: string, display_name: string, reply_to_email: string, reply_to_name: string, bcc_recipients: ?array}
     */
    public static function defaultMailEnvelope(): array
    {
        return [
            'from' => 'support',
            'display_name' => (string) config('mail.from.name', 'AdaptiveQMS'),
            'reply_to_email' => trim((string) config('mail.support_email', 'kay@intelygic.com')),
            'reply_to_name' => (string) config('mail.from.name', 'AdaptiveQMS'),
            'bcc_recipients' => null,
        ];
    }

    protected $table = 'admin_email_templates';

    protected $guarded = ['id', 'uuid', 'slug', 'is_system', 'created_at', 'updated_at'];

    protected $casts = [
        'status' => 'boolean',
        'is_system' => 'boolean',
        'bcc_recipients' => 'array',
        'detail_row_keys' => 'array',
        'detail_row_labels' => 'array',
    ];

    /**
     * @return list<string>
     */
    public static function detailRowCatalogForSlug(string $slug): array
    {
        return [];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function normalizeDetailRowKeysForSlug(array $keys, string $slug): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $labels
     * @return array<string, string>
     */
    public static function normalizeDetailRowLabels(array $labels): array
    {
        return [];
    }

    public function getActivityLogName(): ActivityLogName
    {
        return ActivityLogName::EmailTemplate;
    }

    protected function activityLogExcept(): array
    {
        return ['updated_at', 'created_at', 'uuid', 'slug', 'is_system'];
    }
}
