<?php

namespace App\Http\Controllers\Api\Gpt\V1\Pipeline;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\PipelineRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only access to the pipeline execution log. Runs are created by
 * PipelineController::execute (and, for linked jobs, ScheduledJobController::run);
 * they are not created or mutated directly through this controller.
 */
class PipelineRunController extends GptController
{
    private const STATUSES = ['pending', 'running', 'succeeded', 'failed', 'cancelled'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'pipeline_id'    => 'nullable|integer',
            'status'         => 'nullable|string|in:' . implode(',', self::STATUSES),
            'trigger_source' => 'nullable|string|in:manual,scheduled,webhook,api',
            'limit'          => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = PipelineRun::where('user_id', $user->id)->with('pipeline:id,name,status');

        if ($pipelineId = $request->input('pipeline_id')) {
            $query->where('pipeline_id', $pipelineId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($source = $request->input('trigger_source')) {
            $query->where('trigger_source', $source);
        }

        $runs = $query->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'data'  => $runs->map(fn ($r) => $this->format($r)),
            'count' => $runs->count(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $run = PipelineRun::where('user_id', $this->apiUser($request)->id)
            ->with('pipeline:id,name,status')
            ->findOrFail($id);

        return response()->json(['data' => $this->format($run)]);
    }

    public function format(PipelineRun $r): array
    {
        return [
            'id'             => $r->id,
            'pipeline_id'    => $r->pipeline_id,
            'status'         => $r->status,
            'trigger_source' => $r->trigger_source,
            'input'          => $r->input,
            'output'         => $r->output,
            'error'          => $r->error,
            'started_at'     => $r->started_at?->toISOString(),
            'finished_at'    => $r->finished_at?->toISOString(),
            'pipeline'       => $r->relationLoaded('pipeline') ? $r->pipeline?->only(['id', 'name', 'status']) : null,
            'created_at'     => $r->created_at?->toISOString(),
            'updated_at'     => $r->updated_at?->toISOString(),
        ];
    }
}
