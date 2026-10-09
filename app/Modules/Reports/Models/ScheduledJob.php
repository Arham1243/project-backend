<?php

namespace App\Modules\Reports\Models;

use App\Enums\ActivityLogName;
use App\Traits\AuditsChanges;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ScheduledJob extends Model
{
    use AuditsChanges, HasUuid;

    protected $table = 'admin_scheduled_jobs';

    /** Cached list of enabled jobs for schedule registration (plain arrays, not models). */
    public const CACHE_KEY = 'scheduled-jobs.active';

    protected $fillable = [
        'key',
        'name',
        'description',
        'handler',
        'endpoint',
        'token_config_key',
        'frequency',
        'enabled',
        'last_run_at',
        'last_run_status',
        'last_run_duration',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
        'last_run_duration' => 'decimal:3',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(ScheduledJobRun::class);
    }

    public static function flushCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
            Cache::forget('scheduled-jobs.active.v2');
        } catch (Throwable) {
            //
        }
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }

    public function getActivityLogName(): ActivityLogName
    {
        return ActivityLogName::ScheduledJob;
    }

    protected function activityLogOnly(): ?array
    {
        return ['frequency', 'enabled', 'name', 'description'];
    }
}
