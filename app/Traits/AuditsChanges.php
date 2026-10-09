<?php

namespace App\Traits;

use App\Enums\ActivityLogName;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait AuditsChanges
{
    use LogsActivity;

    abstract public function getActivityLogName(): ActivityLogName;

    /**
     * @return list<string>
     */
    protected function activityLogExcept(): array
    {
        return ['updated_at', 'created_at'];
    }

    /**
     * When set, only these attributes are audited (instead of logAll + except).
     *
     * @return list<string>|null
     */
    protected function activityLogOnly(): ?array
    {
        return null;
    }

    protected function activitySubjectLabel(): ?string
    {
        return $this->defaultActivitySubjectLabel();
    }

    /**
     * Fallback label for models that override activitySubjectLabel().
     * Call this instead of parent:: — the default lives on this trait, not Eloquent.
     */
    protected function defaultActivitySubjectLabel(): ?string
    {
        if (isset($this->attributes['name']) && is_string($this->attributes['name']) && $this->attributes['name'] !== '') {
            return $this->attributes['name'];
        }

        if (isset($this->attributes['label']) && is_string($this->attributes['label']) && $this->attributes['label'] !== '') {
            return $this->attributes['label'];
        }

        return null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        $logName = $this->getActivityLogName()->value;

        $options = LogOptions::defaults()
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty()
            ->useLogName($logName)
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => "Created {$logName}",
                'updated' => "Updated {$logName}",
                'deleted' => "Deleted {$logName}",
                default => ucfirst(str_replace('_', ' ', $eventName))." {$logName}",
            });

        $only = $this->activityLogOnly();
        if ($only !== null) {
            return $options->logOnly($only);
        }

        return $options->logAll()->logExcept($this->activityLogExcept());
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $label = $this->activitySubjectLabel();
        if ($label === null || $label === '') {
            return;
        }

        $properties = $activity->properties;
        $props = $properties instanceof \Illuminate\Support\Collection
            ? $properties->toArray()
            : (array) ($properties ?? []);

        $props['subject_label'] = $label;
        $activity->properties = $props;
    }
}
