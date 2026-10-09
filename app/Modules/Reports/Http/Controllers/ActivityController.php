<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Modules\Administration\Http\Controllers\AdminOrionController;
use App\Modules\Reports\Http\Resources\ActivityResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Orion\Concerns\DisableAuthorization;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends AdminOrionController
{
    use DisableAuthorization;

    protected $model = Activity::class;

    protected $resource = ActivityResource::class;

    public function searchableBy(): array
    {
        return [
            'description',
            'log_name',
        ];
    }

    public function includes(): array
    {
        return ['causer'];
    }

    public function sortableBy(): array
    {
        return [
            'log_name',
            'description',
            'event',
            'subject_type',
            'causer_id',
            'created_at',
        ];
    }

    public function filterableBy(): array
    {
        return [
            'log_name',
            'event',
            'subject_type',
            'causer_id',
            'causer.name',
            'created_at',
        ];
    }

    protected function buildFetchQuery(Request $request, array $requestedRelations): Builder
    {
        $query = parent::buildFetchQuery($request, $requestedRelations);

        $query->where(function ($q) {
            $q->whereRaw("JSON_EXTRACT(properties, '$.attributes') IS NULL")
                ->orWhereRaw("JSON_LENGTH(JSON_EXTRACT(properties, '$.attributes')) != 1")
                ->orWhereRaw("NOT JSON_CONTAINS_PATH(properties, 'one', '$.attributes.password')");
        });

        $this->applyCustomFilters($query, $request);

        return $query->with(['causer:id,name,email,uuid']);
    }

    protected function applyCustomFilters(Builder $query, Request $request): Builder
    {
        if (! $request->filled('customFilters')) {
            return $query;
        }

        $handlers = [
            'created_at_range' => function (Builder $query, $value): void {
                if (is_array($value) && count($value) === 2) {
                    [$from, $to] = $value;
                    if ($from) {
                        $query->whereDate('created_at', '>=', $from);
                    }
                    if ($to) {
                        $query->whereDate('created_at', '<=', $to);
                    }
                }
            },
        ];

        foreach ($request->input('customFilters') as $filter) {
            $field = $filter['field'] ?? null;
            $value = $filter['value'] ?? null;

            if ($field && isset($handlers[$field]) && $value !== null) {
                $handlers[$field]($query, $value);
            }
        }

        return $query;
    }
}
