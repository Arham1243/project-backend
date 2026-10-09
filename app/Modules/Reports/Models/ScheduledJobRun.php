<?php

namespace App\Modules\Reports\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledJobRun extends Model
{
    use BelongsToCompany;
    use HasUuid;

    protected $table = 'admin_scheduled_job_runs';

    public const TRIGGER_SCHEDULE = 'schedule';

    public const TRIGGER_MANUAL = 'manual';

    protected $fillable = [
        'company_id',
        'scheduled_job_id',
        'started_at',
        'finished_at',
        'duration',
        'status',
        'trigger',
        'triggered_by',
        'output',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration' => 'decimal:3',
    ];

    public function scheduledJob(): BelongsTo
    {
        return $this->belongsTo(ScheduledJob::class);
    }
}
