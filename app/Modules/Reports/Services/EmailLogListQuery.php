<?php

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Models\EmailLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class EmailLogListQuery
{
    /**
     * @param  array<int, array<string, mixed>>  $filters
     * @param  array<int, array<string, mixed>>  $customFilters
     * @param  array<int, array<string, mixed>>  $sort
     */
    public static function build(array $filters = [], array $customFilters = [], array $sort = []): Builder
    {
        $query = EmailLog::query()->with([
            'admin:id,name,email',
            'adminEmailTemplate:id,uuid,slug,name',
        ]);

        [$filters, $templateFilter] = self::extractTemplateNameFilter($filters);
        if ($templateFilter !== null) {
            self::applyTemplateNameFilter($query, $templateFilter);
        }

        foreach ($filters as $filter) {
            self::applyFilter($query, $filter);
        }

        foreach ($customFilters as $customFilter) {
            $field = $customFilter['field'] ?? null;
            $value = $customFilter['value'] ?? null;
            if ($field === 'created_at_range' && is_array($value) && count($value) === 2) {
                [$from, $to] = $value;
                if ($from) {
                    $query->whereDate('admin_email_logs.created_at', '>=', $from);
                }
                if ($to) {
                    $query->whereDate('admin_email_logs.created_at', '<=', $to);
                }
            }
        }

        if ($sort === []) {
            $query->orderByDesc('admin_email_logs.created_at');
        } else {
            foreach ($sort as $sortItem) {
                $field = $sortItem['field'] ?? null;
                $direction = strtolower((string) ($sortItem['direction'] ?? 'asc'));
                if (! $field || ! in_array($direction, ['asc', 'desc'], true)) {
                    continue;
                }
                if (Str::contains($field, '.')) {
                    [$relation, $column] = explode('.', $field, 2);
                    $query->whereHas($relation, function (Builder $q) use ($column, $direction): void {
                        $q->orderBy($column, $direction);
                    });
                } else {
                    $query->orderBy('admin_email_logs.'.$field, $direction);
                }
            }
        }

        return $query;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filters
     * @return array{0: array<int, array<string, mixed>>, 1: ?array<string, mixed>}
     */
    private static function extractTemplateNameFilter(array $filters): array
    {
        $remaining = [];
        $templateFilter = null;

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            if (
                $field === 'adminEmailTemplate.name'
                && ($filter['operator'] ?? '') === 'like'
            ) {
                $templateFilter = $filter;
                continue;
            }
            $remaining[] = $filter;
        }

        return [$remaining, $templateFilter];
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private static function applyFilter(Builder $query, array $filter): void
    {
        $field = $filter['field'] ?? null;
        if ($field === null || $field === '') {
            return;
        }

        $operator = $filter['operator'] ?? '=';
        $value = $filter['value'] ?? null;

        if ($value === null || $value === '') {
            return;
        }

        if (Str::contains($field, '.')) {
            [$relation, $column] = explode('.', $field, 2);
            $query->whereHas($relation, function (Builder $inner) use ($column, $operator, $value, $field): void {
                if ($field === 'admin.name' && $operator === 'like') {
                    $inner->where(function (Builder $q) use ($column, $value): void {
                        $q->where('name', 'like', $value)
                            ->orWhere('email', 'like', $value);
                    });

                    return;
                }
                if ($operator === 'like') {
                    $inner->where($column, 'like', $value);
                } else {
                    $inner->where($column, $operator, $value);
                }
            });

            return;
        }

        $column = 'admin_email_logs.'.$field;
        if ($operator === 'like') {
            $query->where($column, 'like', $value);
        } elseif ($operator === 'in' && is_array($value)) {
            $query->whereIn($column, $value);
        } else {
            $query->where($column, $operator, $value);
        }
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private static function applyTemplateNameFilter(Builder $query, array $filter): void
    {
        $likeValue = $filter['value'] ?? '';
        if (! is_string($likeValue) || $likeValue === '') {
            return;
        }

        $plain = trim($likeValue, '%');
        $slugLike = '%'.str_replace(
            ['%', '_'],
            ['\%', '\_'],
            str_replace(' ', '_', strtolower($plain))
        ).'%';

        $query->where(function (Builder $inner) use ($likeValue, $slugLike): void {
            $inner
                ->whereHas('adminEmailTemplate', function (Builder $templateQuery) use ($likeValue): void {
                    $templateQuery->where('name', 'like', $likeValue);
                })
                ->orWhere('admin_email_logs.template_slug', 'like', $likeValue)
                ->orWhere('admin_email_logs.template_slug', 'like', $slugLike);
        });
    }
}
