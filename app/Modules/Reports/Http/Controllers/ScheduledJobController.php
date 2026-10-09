<?php

namespace App\Modules\Reports\Http\Controllers;
use App\Modules\Administration\Http\Controllers\AdminOrionController;

use App\Modules\Reports\Http\Requests\ScheduledJobRequest;
use App\Modules\Reports\Http\Resources\ScheduledJobResource;
use App\Modules\Reports\Http\Resources\ScheduledJobRunResource;
use App\Modules\Reports\Models\ScheduledJob;
use App\Modules\Reports\Services\ScheduledJobManualRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Orion\Concerns\DisableAuthorization;

class ScheduledJobController extends AdminOrionController
{
    use DisableAuthorization;

    protected $model = ScheduledJob::class;

    protected $request = ScheduledJobRequest::class;

    protected $resource = ScheduledJobResource::class;

    public function searchableBy(): array
    {
        return ['name', 'key', 'description'];
    }

    public function sortableBy(): array
    {
        return [
            'name',
            'key',
            'enabled',
            'last_run_at',
            'last_run_status',
            'last_run_duration',
            'created_at',
        ];
    }

    public function filterableBy(): array
    {
        return ['enabled', 'last_run_status', 'name', 'frequency'];
    }

    /**
     * Paginated run history for a single scheduled job, latest first.
     */
    public function runs(Request $request, ScheduledJob $scheduledJob): JsonResponse
    {
        $validated = $request->validate([
            'started_after' => ['nullable', 'date'],
            'started_before' => ['nullable', 'date'],
        ]);

        $limit = (int) $request->query('limit', 25);
        $limit = max(1, min($limit, 100));

        $query = $scheduledJob->runs()->orderByDesc('started_at');

        if (! empty($validated['started_after'])) {
            $query->where('started_at', '>=', $validated['started_after'].' 00:00:00');
        }

        if (! empty($validated['started_before'])) {
            $query->where('started_at', '<=', $validated['started_before'].' 23:59:59');
        }

        $runs = $query->paginate($limit);

        return ScheduledJobRunResource::collection($runs)->response();
    }

    /**
     * Execute a scheduled job immediately (does not alter its cron schedule).
     */
    public function run(
        Request $request,
        ScheduledJob $scheduledJob,
        ScheduledJobManualRunService $manualRunService,
    ): JsonResponse {
        if (! $scheduledJob->enabled) {
            return response()->json([
                'message' => 'This scheduled job is disabled and cannot be run.',
            ], 422);
        }

        $admin = $request->user();
        $adminId = $admin !== null ? (int) $admin->getAuthIdentifier() : 0;

        $result = $manualRunService->run($scheduledJob, $adminId);

        if ($result === null) {
            return response()->json([
                'message' => 'This job is already running. Try again when the current run finishes.',
            ], 409);
        }

        $scheduledJob->refresh();

        return response()->json([
            'data' => [
                'job' => new ScheduledJobResource($scheduledJob),
                'run' => new ScheduledJobRunResource($result['run']),
            ],
        ]);
    }
}
