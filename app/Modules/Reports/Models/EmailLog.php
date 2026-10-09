<?php

namespace App\Modules\Reports\Models;

use App\Modules\Administration\Models\Admin\AdminEmailTemplate;
use App\Modules\Administration\Models\User;
use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use BelongsToCompany;
    use HasUuid;

    protected $table = 'admin_email_logs';

    protected $fillable = [
        'company_id',
        'admin_id',
        'entity_type',
        'entity_id',
        'template_slug',
        'admin_email_template_id',
        'recipient',
        'subject',
        'mailgun_message_id',
        'mailgun_send_message',
        'status',
        'failure_reason',
        'mailgun_severity',
        'mailgun_last_event_ts',
        'mailgun_events_snapshot',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'mailgun_events_snapshot' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adminEmailTemplate(): BelongsTo
    {
        return $this->belongsTo(AdminEmailTemplate::class, 'admin_email_template_id');
    }
}
