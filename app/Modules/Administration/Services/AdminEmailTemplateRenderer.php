<?php

namespace App\Modules\Administration\Services;

use App\Modules\Administration\Models\Admin\AdminEmailTemplate;

/**
 * Resolves HTML for outbound emails driven by {@see AdminEmailTemplate} rows.
 */
class AdminEmailTemplateRenderer
{
    public function interpolate(?string $html, array $vars): string
    {
        if ($html === null) {
            return '';
        }
        $out = $html;
        foreach ($vars as $key => $value) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }
            $key = (string) $key;
            if ($key === '') {
                continue;
            }
            $replacement = (string) $value;
            $pattern = '/\{\{\s*'.preg_quote($key, '/').'\s*\}\}/u';
            $replaced = preg_replace($pattern, $replacement, $out);
            $out = $replaced ?? $out;
        }

        return $out;
    }

    public function normalizeMailSubject(string $subject): string
    {
        $subject = trim($subject);
        $subject = preg_replace('/\s*-\s*$/', '', $subject) ?? $subject;
        $subject = preg_replace('/:\s*$/', '', $subject) ?? $subject;

        if (strlen($subject) > 200) {
            $subject = substr($subject, 0, 197).'...';
        }

        return $subject;
    }

    /**
     * @return array{html: string, subject: string}
     */
    public function buildPreview(string $slug, array $fieldOverrides = [], array $placeholderOverrides = []): array
    {
        if (! AdminEmailTemplate::isAuthSlug($slug)) {
            return [
                'html' => '<p>Preview is not available for this template.</p>',
                'subject' => AdminEmailTemplate::defaultSubjectTemplateForSlug($slug),
            ];
        }

        return $this->buildAuthEmail($slug, $fieldOverrides, $placeholderOverrides);
    }

    /**
     * @return array<string, string>
     */
    public function authPreviewPlaceholders(): array
    {
        return [
            'name' => 'User',
            'otp_code' => '123456',
            'expires_in_minutes' => '10',
            'verify_link' => '',
            'year' => now()->format('Y'),
        ];
    }

    /**
     * @return array{html: string, subject: string}
     */
    public function buildAuthEmail(string $slug, array $fieldOverrides = [], array $placeholderOverrides = []): array
    {
        $placeholders = array_merge($this->authPreviewPlaceholders(), $placeholderOverrides);
        $row = $this->activeRow($slug);

        $subjectTemplate = null;
        if (array_key_exists('subject_template', $fieldOverrides)) {
            $subjectTemplate = $fieldOverrides['subject_template'];
        } elseif ($row !== null && filled($row->subject_template)) {
            $subjectTemplate = $row->subject_template;
        } else {
            $subjectTemplate = AdminEmailTemplate::defaultSubjectTemplateForSlug($slug);
        }

        $intro = $this->interpolate(
            $fieldOverrides['intro_html'] ?? $row?->intro_html,
            $placeholders
        );
        $body = $this->interpolate(
            $fieldOverrides['body_html'] ?? $row?->body_html,
            $placeholders
        );
        $footer = $this->interpolate(
            $fieldOverrides['footer_html'] ?? $row?->footer_html ?? AdminEmailTemplate::DEFAULT_COPYRIGHT_LINE,
            $placeholders
        );

        $subject = $this->normalizeMailSubject($this->interpolate($subjectTemplate, $placeholders));

        $verifyLink = trim((string) ($placeholders['verify_link'] ?? ''));
        $isPasswordSlug = in_array($slug, [
            AdminEmailTemplate::SLUG_RESET_PASSWORD,
            AdminEmailTemplate::SLUG_SETUP_PASSWORD,
        ], true);

        $ctaUrl = null;
        $ctaAlign = 'left';
        if ($isPasswordSlug) {
            $ctaUrl = $verifyLink !== '' ? $verifyLink : '#';
            $ctaAlign = 'center';
        }

        $otpCode = $slug === AdminEmailTemplate::SLUG_LOGIN_OTP
            ? ($placeholders['otp_code'] ?? null)
            : null;

        $ctaLabel = AdminEmailTemplate::defaultCtaLabelForSlug($slug) ?? 'Continue';

        $html = view('emails.admin._layout', [
            'headline' => '',
            'greetingName' => '',
            'htmlTitle' => $subject,
            'introHtml' => $intro,
            'bodyHtml' => filled($body) ? $body : null,
            'rows' => [],
            'ticket' => null,
            'ctaUrl' => $ctaUrl,
            'ctaLabel' => $ctaLabel,
            'ctaAlign' => $ctaAlign,
            'companyId' => null,
            'copyrightLine' => $footer,
            'otpCode' => filled($otpCode) ? (string) $otpCode : null,
        ])->render();

        return ['html' => $html, 'subject' => $subject];
    }

    protected function activeRow(string $slug): ?AdminEmailTemplate
    {
        $query = AdminEmailTemplate::query()
            ->where('slug', $slug)
            ->where('status', true);

        $user = auth()->user();
        if ($user && isset($user->company_id) && $user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return $query->first();
    }

    public function renderPreviewHtml(string $slug, array $fieldOverrides = [], array $placeholderOverrides = []): string
    {
        return $this->buildPreview($slug, $fieldOverrides, $placeholderOverrides)['html'];
    }
}
