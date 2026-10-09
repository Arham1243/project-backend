<?php

namespace App\Modules\Reports\Http\Controllers;
use App\Modules\Administration\Http\Controllers\AdminOrionController;

use App\Modules\Reports\Http\Resources\EmailLogResource;
use App\Modules\Reports\Models\EmailLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Orion\Concerns\DisableAuthorization;

class EmailLogController extends AdminOrionController
{
    use DisableAuthorization;

    protected $model = EmailLog::class;

    protected $resource = EmailLogResource::class;

    public function searchableBy(): array
    {
        return [
            'recipient',
            'subject',
            'template_slug',
            'adminEmailTemplate.name',
            'mailgun_message_id',
        ];
    }

    public function includes(): array
    {
        return ['admin', 'adminEmailTemplate'];
    }

    public function sortableBy(): array
    {
        return [
            'created_at',
            'status',
            'recipient',
            'template_slug',
            'entity_type',
            'admin_id',
            'admin_email_template_id',
        ];
    }

    public function filterableBy(): array
    {
        return [
            'status',
            'template_slug',
            'entity_type',
            'entity_id',
            'admin_id',
            'admin.name',
            'recipient',
            'subject',
            'mailgun_message_id',
            'admin_email_template_id',
            'adminEmailTemplate.name',
        ];
    }

    protected function buildFetchQuery(Request $request, array $requestedRelations): Builder
    {
        $templateNameFilter = $this->extractTemplateNameFilter($request);

        $query = parent::buildFetchQuery($request, $requestedRelations);

        if ($templateNameFilter !== null) {
            $this->applyTemplateNameFilter($query, $templateNameFilter);
        }

        $query->with([
            'admin:id,name,email',
            'adminEmailTemplate:id,uuid,slug,name',
        ]);

        $this->applyCustomFilters($query, $request);

        return $query;
    }

    protected function extractTemplateNameFilter(Request $request): ?array
    {
        $filters = $request->input('filters', []);
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

        if ($templateFilter === null) {
            return null;
        }

        $request->merge(['filters' => $remaining]);

        return $templateFilter;
    }

    protected function applyTemplateNameFilter(Builder $query, array $filter): void
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

    protected function applyCustomFilters(Builder $query, Request $request): Builder
    {
        if (! $request->filled('customFilters')) {
            return $query;
        }

        $handlers = [
            'created_at_range' => function (Builder $query, mixed $value): void {
                if (is_array($value) && count($value) === 2) {
                    [$from, $to] = $value;
                    if ($from) {
                        $query->whereDate('admin_email_logs.created_at', '>=', $from);
                    }
                    if ($to) {
                        $query->whereDate('admin_email_logs.created_at', '<=', $to);
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
