<?php

namespace App\Modules\Administration\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use BelongsToCompany;
    use HasUuid;

    protected $fillable = [
        'uuid',
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'event',
        'causer_type',
        'causer_id',
        'properties',
        'batch_uuid',
        'company_id',
    ];
}
