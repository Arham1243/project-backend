<?php

namespace App\Modules\Administration\Http\Controllers;
use App\Modules\Administration\Http\Controllers\AdminOrionController;

use App\Modules\Administration\Http\Resources\AdminEmailTemplateResource;
use App\Modules\Administration\Models\Admin\AdminEmailTemplate;
use App\Modules\Administration\Services\AdminEmailTemplateRenderer;
use App\Services\MailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Orion\Concerns\DisableAuthorization;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminEmailTemplateController extends AdminOrionController
{
    use DisableAuthorization;

    protected $model = AdminEmailTemplate::class;

    protected $resource = AdminEmailTemplateResource::class;

    public function searchableBy(): array
    {
        return ['name', 'slug'];
    }

    public function sortableBy(): array
    {
        return ['name', 'slug', 'status', 'created_at', 'is_system'];
    }

    public function filterableBy(): array
    {
        return ['status', 'slug', 'is_system'];
    }

    protected function buildIndexFetchQuery(Request $request, array $requestedRelations): Builder
    {
        return parent::buildIndexFetchQuery($request, $requestedRelations)
            ->orderByDesc('status')
            ->orderBy('name');
    }

    protected function performStore(Request $request, Model $entity, array $attributes): void
    {
        throw new HttpException(405, 'Creating admin email templates via API is not supported.');
    }

    protected function performUpdate(Request $request, Model $entity, array $attributes): void
    {
        $data = $this->validatePayload($request, $entity);

        $entity->fill($data);
        $entity->save();
    }

    protected function performDestroy(Model $entity): void
    {
        throw new HttpException(405, 'Deleting admin email templates is not supported.');
    }

    public function changeStatus(Request $request, AdminEmailTemplate $adminEmailTemplate): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'boolean'],
        ]);

        $adminEmailTemplate->update(['status' => $validated['status']]);

        return AdminEmailTemplateResource::make($adminEmailTemplate->fresh())->response();
    }

    public function preview(Request $request, AdminEmailTemplate $adminEmailTemplate)
    {
        [$fieldOverrides, $placeholderOverrides] = $this->validatedPreviewInput(
            $request,
            $adminEmailTemplate
        );

        $preview = $this->renderPreview(
            $adminEmailTemplate,
            $fieldOverrides,
            $placeholderOverrides
        );

        return response($preview['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Resolved-Email-Subject' => $preview['subject'],
        ]);
    }

    public function sendTest(Request $request, AdminEmailTemplate $adminEmailTemplate): JsonResponse
    {
        $envelope = $request->validate([
            'recipient_email' => ['required', 'string', 'email', 'max:255'],
            'from' => ['sometimes', 'string', 'not_regex:/[@\s]/'],
            'display_name' => ['sometimes', 'string', 'not_regex:/@/'],
            'reply_to_email' => ['sometimes', 'string', 'email'],
            'reply_to_name' => ['sometimes', 'string', 'not_regex:/@/'],
            'bcc_recipients' => ['nullable', 'array'],
            'bcc_recipients.*' => ['email'],
        ], [
            'display_name.not_regex' => 'Display Name cannot contain an email address.',
            'from.not_regex' => 'From cannot contain an email address or spaces.',
            'reply_to_name.not_regex' => 'Reply To Name cannot contain an email address.',
        ]);

        [$fieldOverrides, $placeholderOverrides] = $this->validatedPreviewInput(
            $request,
            $adminEmailTemplate
        );

        $preview = $this->renderPreview(
            $adminEmailTemplate,
            $fieldOverrides,
            $placeholderOverrides
        );

        $sendTemplate = (object) [
            'from' => $envelope['from'] ?? $adminEmailTemplate->from,
            'display_name' => $envelope['display_name'] ?? $adminEmailTemplate->display_name,
            'reply_to_email' => $envelope['reply_to_email'] ?? $adminEmailTemplate->reply_to_email,
            'reply_to_name' => $envelope['reply_to_name'] ?? $adminEmailTemplate->reply_to_name,
            'bcc_recipients' => array_key_exists('bcc_recipients', $envelope)
                ? $envelope['bcc_recipients']
                : $adminEmailTemplate->bcc_recipients,
            'slug' => $adminEmailTemplate->slug,
        ];

        $admin = $request->user();

        $result = MailService::sendEmail([
            'company' => MailService::systemMailgunCompany(),
            'to' => $envelope['recipient_email'],
            'subject' => $preview['subject'],
            'html' => $preview['html'],
            'template' => $sendTemplate,
            'user_id' => $admin?->id,
            'entity_type' => MailService::ENTITY_SYSTEM,
            'mailgun_tags' => ['test-email'],
        ]);

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'recipient_email' => [$result['message'] ?? 'Failed to send test email.'],
            ]);
        }

        return response()->json(['message' => 'Test email sent successfully.']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function validatedPreviewInput(
        Request $request,
        AdminEmailTemplate $adminEmailTemplate
    ): array {
        $catalog = AdminEmailTemplate::detailRowCatalogForSlug($adminEmailTemplate->slug);
        $rules = [
            'intro_html' => ['nullable', 'string'],
            'body_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string', 'max:500'],
            'subject_template' => ['nullable', 'string', 'max:255'],
        ];
        if ($catalog !== []) {
            $rules['detail_row_keys'] = ['nullable', 'array'];
            $rules['detail_row_keys.*'] = ['string', Rule::in($catalog)];
            $rules['detail_row_labels'] = ['nullable', 'array'];
        }

        $validated = $request->validate($rules);

        $fieldOverrides = [];
        foreach (['intro_html', 'body_html', 'footer_html', 'subject_template', 'detail_row_keys', 'detail_row_labels'] as $key) {
            if (array_key_exists($key, $validated)) {
                $fieldOverrides[$key] = $validated[$key];
            }
        }

        if (array_key_exists('detail_row_keys', $fieldOverrides) && is_array($fieldOverrides['detail_row_keys'])) {
            $fieldOverrides['detail_row_keys'] = AdminEmailTemplate::normalizeDetailRowKeysForSlug(
                $fieldOverrides['detail_row_keys'],
                $adminEmailTemplate->slug
            );
        }

        if (array_key_exists('detail_row_labels', $fieldOverrides)) {
            $fieldOverrides['detail_row_labels'] = is_array($fieldOverrides['detail_row_labels'])
                ? AdminEmailTemplate::normalizeDetailRowLabels($fieldOverrides['detail_row_labels'])
                : [];
        }

        return [$fieldOverrides, []];
    }

    /**
     * @return array{html: string, subject: string}
     */
    private function renderPreview(
        AdminEmailTemplate $adminEmailTemplate,
        array $fieldOverrides,
        array $placeholderOverrides
    ): array {
        $renderer = app(AdminEmailTemplateRenderer::class);

        return $renderer->buildPreview(
            $adminEmailTemplate->slug,
            $fieldOverrides,
            $placeholderOverrides
        );
    }

    /**
     * @param  AdminEmailTemplate  $entity
     */
    private function validatePayload(Request $request, Model $entity): array
    {
        $slug = (string) $entity->slug;
        $catalog = AdminEmailTemplate::detailRowCatalogForSlug($slug);

        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'from' => ['required', 'string', 'not_regex:/[@\s]/'],
            'display_name' => ['required', 'string', 'not_regex:/@/'],
            'reply_to_email' => ['required', 'string', 'email'],
            'reply_to_name' => ['required', 'string', 'not_regex:/@/'],
            'bcc_recipients' => ['nullable', 'array'],
            'bcc_recipients.*' => ['email'],
            'subject_template' => ['nullable', 'string', 'max:255'],
            'intro_html' => ['nullable', 'string'],
            'body_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'boolean'],
        ];

        if ($catalog !== []) {
            $rules['detail_row_keys'] = ['nullable', 'array'];
            $rules['detail_row_keys.*'] = ['string', Rule::in($catalog)];
            $rules['detail_row_labels'] = ['nullable', 'array'];
        }

        $data = $request->validate($rules, [
            'display_name.not_regex' => 'Display Name cannot contain an email address.',
            'from.not_regex' => 'From cannot contain an email address or spaces.',
            'reply_to_name.not_regex' => 'Reply To Name cannot contain an email address.',
        ]);

        if (array_key_exists('detail_row_keys', $data) && is_array($data['detail_row_keys'])) {
            $data['detail_row_keys'] = AdminEmailTemplate::normalizeDetailRowKeysForSlug($data['detail_row_keys'], $slug);
        }

        if (array_key_exists('detail_row_labels', $data)) {
            $labels = is_array($data['detail_row_labels'])
                ? AdminEmailTemplate::normalizeDetailRowLabels($data['detail_row_labels'])
                : [];
            $data['detail_row_labels'] = $labels === [] ? null : $labels;
        }

        return $data;
    }
}
