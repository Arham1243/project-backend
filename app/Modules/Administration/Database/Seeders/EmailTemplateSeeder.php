<?php

namespace App\Modules\Administration\Database\Seeders;

use App\Modules\Administration\Models\Admin\AdminEmailTemplate;
use App\Modules\Core\Models\Company;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->orderBy('id')->first();
        if (! $company) {
            return;
        }

        $mail = AdminEmailTemplate::defaultMailEnvelope();

        $templates = [
            [
                'slug' => AdminEmailTemplate::SLUG_LOGIN_OTP,
                'name' => 'Login OTP Email to Admin',
                'from' => 'verification',
                'intro_html' => '<p style="margin:0;">Hello {{name}},</p>',
                'body_html' => '<p style="margin:0;">We received a login attempt from a new device. To verify your identity, please use the verification code displayed below.</p>'
                    .'<p style="margin:12px 0 0 0;">This code will expire in {{expires_in_minutes}} minutes. If you didn\'t attempt to log in, please ignore this email.</p>',
                'footer_html' => AdminEmailTemplate::DEFAULT_COPYRIGHT_LINE,
            ],
            [
                'slug' => AdminEmailTemplate::SLUG_RESET_PASSWORD,
                'name' => 'Password Reset Email to Admin',
                'from' => 'info',
                'intro_html' => '<p style="margin:0;">Hi {{name}},</p>',
                'body_html' => '<p style="margin:0;">Looks like you forgot your password. No worries! We\'ll help you reset it just click the button below.</p>',
                'footer_html' => AdminEmailTemplate::DEFAULT_COPYRIGHT_LINE,
            ],
            [
                'slug' => AdminEmailTemplate::SLUG_SETUP_PASSWORD,
                'name' => 'Welcome Email to Admin',
                'from' => 'info',
                'intro_html' => '<p style="margin:0;">Hi {{name}},</p>',
                'body_html' => '<p style="margin:0;">Welcome aboard! To get started, simply activate your account by clicking the button below.</p>',
                'footer_html' => AdminEmailTemplate::DEFAULT_COPYRIGHT_LINE,
            ],
        ];

        foreach ($templates as $row) {
            $slug = $row['slug'];
            $attributes = [
                'slug' => $slug,
                'name' => $row['name'],
                'subject_template' => $row['subject_template']
                    ?? AdminEmailTemplate::defaultSubjectTemplateForSlug($slug),
                'from' => $row['from'] ?? $mail['from'],
                'display_name' => $mail['display_name'],
                'reply_to_email' => $mail['reply_to_email'],
                'reply_to_name' => $mail['reply_to_name'],
                'bcc_recipients' => $mail['bcc_recipients'],
                'intro_html' => $row['intro_html'],
                'body_html' => $row['body_html'],
                'footer_html' => $row['footer_html'],
                'status' => true,
                'is_system' => true,
            ];

            $template = AdminEmailTemplate::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('slug', $slug)
                ->first();
            if ($template === null) {
                $template = new AdminEmailTemplate;
                $template->company_id = $company->id;
            }
            $template->forceFill($attributes);
            $template->save();
        }

        AdminEmailTemplate::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNotIn('slug', array_column($templates, 'slug'))
            ->delete();
    }
}
