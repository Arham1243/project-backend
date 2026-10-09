<?php

namespace App\Modules\Administration\Http\Resources;

use App\Modules\Administration\Models\Admin\AdminEmailTemplate;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminEmailTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        $mail = AdminEmailTemplate::defaultMailEnvelope();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'from' => filled($this->from) ? $this->from : $mail['from'],
            'display_name' => filled($this->display_name) ? $this->display_name : $mail['display_name'],
            'reply_to_email' => filled($this->reply_to_email) ? $this->reply_to_email : $mail['reply_to_email'],
            'reply_to_name' => filled($this->reply_to_name) ? $this->reply_to_name : $mail['reply_to_name'],
            'bcc_recipients' => is_array($this->bcc_recipients) ? $this->bcc_recipients : [],
            'subject_template' => filled($this->subject_template)
                ? $this->subject_template
                : AdminEmailTemplate::defaultSubjectTemplateForSlug($this->slug),
            'intro_html' => $this->intro_html,
            'body_html' => $this->body_html,
            'footer_html' => $this->footer_html !== null && $this->footer_html !== ''
                ? $this->footer_html
                : AdminEmailTemplate::DEFAULT_COPYRIGHT_LINE,
            'detail_row_keys' => is_array($this->detail_row_keys)
                ? AdminEmailTemplate::normalizeDetailRowKeysForSlug($this->detail_row_keys, $this->slug)
                : $this->detail_row_keys,
            'detail_row_labels' => $this->detail_row_labels,
            'detail_row_catalog' => AdminEmailTemplate::detailRowCatalogForSlug($this->slug),
            'status' => (bool) $this->status,
            'is_system' => (bool) $this->is_system,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
