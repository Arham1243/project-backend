<?php

namespace App\Modules\Reports\Http\Resources;

use App\Http\Resources\Concerns\OmitsRedundantKeys;
use App\Modules\Administration\Models\Admin\AdminEmailTemplate;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailLogResource extends JsonResource
{
    use OmitsRedundantKeys;

    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            'admin' => $this->whenLoaded('admin', function () {
                if ($this->admin === null) {
                    return null;
                }

                return [
                    'id' => $this->admin->id,
                    'name' => $this->admin->name,
                    'email' => $this->admin->email,
                ];
            }),
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'template_slug' => $this->template_slug,
            'email_template_uuid' => $this->adminEmailTemplate?->uuid ?: self::resolveEmailTemplateUuid(
                $this->admin_email_template_id,
                $this->template_slug
            ),
            'email_template_name' => $this->adminEmailTemplate?->name,
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'mailgun_message_id' => $this->mailgun_message_id,
            'mailgun_send_message' => $this->mailgun_send_message,
            'status' => $this->status,
            'failure_reason' => $this->failure_reason,
            'mailgun_severity' => $this->mailgun_severity,
            'mailgun_last_event_ts' => $this->mailgun_last_event_ts,
            'mailgun_events_snapshot' => $this->mailgun_events_snapshot,
            'last_synced_at' => $this->last_synced_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if (! $this->relationLoaded('admin')) {
            $data['admin_id'] = $this->admin_id;
            $data['admin_name'] = $this->admin?->name;
        }

        if (! $this->relationLoaded('adminEmailTemplate')) {
            $data['admin_email_template_id'] = $this->admin_email_template_id;
        }

        $data = $this->omitIfRelationPresent($data, 'admin_id', 'admin');
        $data = $this->omitIfRelationPresent($data, 'admin_name', 'admin');

        if ($this->relationLoaded('adminEmailTemplate') && $this->adminEmailTemplate !== null) {
            unset($data['admin_email_template_id']);
        }

        return $data;
    }

    private static function resolveEmailTemplateUuid(
        mixed $adminEmailTemplateId,
        ?string $templateSlug
    ): ?string {
        static $byId = [];
        static $bySlug = [];

        if ($adminEmailTemplateId !== null && $adminEmailTemplateId !== '') {
            $id = (int) $adminEmailTemplateId;
            if ($id > 0) {
                if (! array_key_exists($id, $byId)) {
                    $byId[$id] = AdminEmailTemplate::query()->whereKey($id)->value('uuid');
                }
                if (! empty($byId[$id])) {
                    return $byId[$id];
                }
            }
        }

        if ($templateSlug === null || $templateSlug === '') {
            return null;
        }

        if (! array_key_exists($templateSlug, $bySlug)) {
            $bySlug[$templateSlug] = AdminEmailTemplate::query()
                ->where('slug', $templateSlug)
                ->value('uuid');
        }

        return $bySlug[$templateSlug] ?: null;
    }
}
