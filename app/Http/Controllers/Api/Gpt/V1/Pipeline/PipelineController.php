<?php

namespace App\Http\Controllers\Api\Gpt\V1\Pipeline;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends GptController
{
    private const TRIGGER_TYPES = ['manual', 'scheduled', 'webhook'];
    private const STATUSES      = ['active', 'paused', 'archived'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'       => 'nullable|string|in:' . implode(',', self::STATUSES),
            'trigger_type' => 'nullable|string|in:' . implode(',', self::TRIGGER_TYPES),
            'search'       => 'nullable|string|max:200',
            'limit'        => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = Pipeline::where('user_id', $user->id)->withCount('runs');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($triggerType = $request->input('trigger_type')) {
            $query->where('trigger_type', $triggerType);
        }
        if ($search = $request->input('search')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $pipelines = $query->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'data'  => $pipelines->map(fn ($p) => $this->format($p)),
            'count' => $pipelines->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:5000',
            'trigger_type' => 'nullable|in:' . implode(',', self::TRIGGER_TYPES),
            'status'       => 'nullable|in:' . implode(',', self::STATUSES),
            'steps'        => 'nullable|array',
            'config'       => 'nullable|array',
            'meta'         => 'nullable|array',
        ]);

        $user = $this->apiUser($request);

        $pipeline = Pipeline::create([
            'user_id'      => $user->id,
            'tenant_id'    => $user->tenant_id,
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'trigger_type' => $data['trigger_type'] ?? 'manual',
            'status'       => $data['status'] ?? 'active',
            'steps'        => $data['steps'] ?? null,
            'config'       => $data['config'] ?? null,
            'meta'         => $data['meta'] ?? null,
        ]);

        $this->audit($request, 'create_pipeline', 'pipeline', $pipeline->id, 'low',
            "name={$pipeline->name}, trigger={$pipeline->trigger_type}", "id={$pipeline->id}");

        return response()->json(['data' => $this->format($pipeline)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $pipeline = Pipeline::where('user_id', $this->apiUser($request)->id)
            ->withCount('runs')
            ->findOrFail($id);

        return response()->json(['data' => $this->format($pipeline)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'description'  => 'sometimes|nullable|string|max:5000',
            'trigger_type' => 'sometimes|in:' . implode(',', self::TRIGGER_TYPES),
            'status'       => 'sometimes|in:' . implode(',', self::STATUSES),
            'steps'        => 'sometimes|nullable|array',
            'config'       => 'sometimes|nullable|array',
            'meta'         => 'sometimes|nullable|array',
        ]);

        $user     = $this->apiUser($request);
        $pipeline = Pipeline::where('user_id', $user->id)->findOrFail($id);

        foreach (['name', 'description', 'trigger_type', 'status', 'steps', 'config', 'meta'] as $field) {
            if (array_key_exists($field, $data)) {
                $pipeline->{$field} = $data[$field];
            }
        }

        $pipeline->save();

        $this->audit($request, 'update_pipeline', 'pipeline', $pipeline->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$pipeline->id}");

        $pipeline->loadCount('runs');

        return response()->json(['data' => $this->format($pipeline)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $pipeline = Pipeline::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $pipeline->delete();

        $this->audit($request, 'delete_pipeline', 'pipeline', $id, 'low', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Trigger a pipeline execution. This records a PipelineRun and stamps the
     * pipeline's run counters — it does NOT execute step logic inline (there is
     * no execution engine; runs are recorded as pending for an external worker
     * to process). Paused/archived pipelines cannot be executed.
     */
    public function execute(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'input'          => 'nullable|array',
            'trigger_source' => 'nullable|string|in:manual,scheduled,webhook,api',
        ]);

        $user     = $this->apiUser($request);
        $pipeline = Pipeline::where('user_id', $user->id)->findOrFail($id);

        if ($pipeline->status !== 'active') {
            return response()->json([
                'error'  => "Pipeline cannot be executed while status is '{$pipeline->status}'. Only active pipelines can run.",
                'status' => $pipeline->status,
            ], 422);
        }

        $run = PipelineRun::create([
            'user_id'        => $user->id,
            'tenant_id'      => $user->tenant_id,
            'pipeline_id'    => $pipeline->id,
            'status'         => 'pending',
            'trigger_source' => $data['trigger_source'] ?? 'api',
            'input'          => $data['input'] ?? null,
            'started_at'     => now(),
        ]);

        $pipeline->forceFill([
            'last_run_at' => now(),
            'run_count'   => $pipeline->run_count + 1,
        ])->save();

        $this->audit($request, 'execute_pipeline', 'pipeline', $pipeline->id, 'medium',
            "id={$pipeline->id}, source={$run->trigger_source}", "run_id={$run->id}");

        $pipeline->loadCount('runs');

        return response()->json([
            'data' => $this->format($pipeline),
            'run'  => $this->formatRun($run),
        ], 201);
    }

    public function format(Pipeline $p): array
    {
        return [
            'id'           => $p->id,
            'name'         => $p->name,
            'description'  => $p->description,
            'trigger_type' => $p->trigger_type,
            'status'       => $p->status,
            'steps'        => $p->steps,
            'config'       => $p->config,
            'meta'         => $p->meta,
            'run_count'    => $p->run_count,
            'runs_count'   => $p->runs_count ?? null,
            'last_run_at'  => $p->last_run_at?->toISOString(),
            'created_at'   => $p->created_at?->toISOString(),
            'updated_at'   => $p->updated_at?->toISOString(),
        ];
    }

    private function formatRun(PipelineRun $r): array
    {
        return [
            'id'             => $r->id,
            'pipeline_id'    => $r->pipeline_id,
            'status'         => $r->status,
            'trigger_source' => $r->trigger_source,
            'input'          => $r->input,
            'started_at'     => $r->started_at?->toISOString(),
            'created_at'     => $r->created_at?->toISOString(),
        ];
    }
}
