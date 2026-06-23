<?php

namespace App\Http\Controllers\Api\Gpt\V1\Pipeline;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\ScheduledJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduledJobController extends GptController
{
    private const FREQUENCIES = ['once', 'hourly', 'daily', 'weekly', 'monthly', 'cron'];
    private const STATUSES    = ['active', 'paused'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'      => 'nullable|string|in:' . implode(',', self::STATUSES),
            'job_type'    => 'nullable|string|max:100',
            'pipeline_id' => 'nullable|integer',
            'search'      => 'nullable|string|max:200',
            'limit'       => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = ScheduledJob::where('user_id', $user->id)->with('pipeline:id,name,status');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($jobType = $request->input('job_type')) {
            $query->where('job_type', $jobType);
        }
        if ($pipelineId = $request->input('pipeline_id')) {
            $query->where('pipeline_id', $pipelineId);
        }
        if ($search = $request->input('search')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $jobs = $query->orderByDesc('id')->limit($limit)->get();

        return response()->json([
            'data'  => $jobs->map(fn ($j) => $this->format($j)),
            'count' => $jobs->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string|max:5000',
            'job_type'        => 'nullable|string|max:100',
            'pipeline_id'     => 'nullable|integer',
            'frequency'       => 'nullable|in:' . implode(',', self::FREQUENCIES),
            'cron_expression' => 'nullable|string|max:255',
            'run_at'          => 'nullable|date',
            'next_run_at'     => 'nullable|date',
            'status'          => 'nullable|in:' . implode(',', self::STATUSES),
            'payload'         => 'nullable|array',
            'meta'            => 'nullable|array',
        ]);

        $user = $this->apiUser($request);

        $pipeline = $this->resolvePipeline($user->id, $data['pipeline_id'] ?? null);

        $job = ScheduledJob::create([
            'user_id'         => $user->id,
            'tenant_id'       => $user->tenant_id,
            'pipeline_id'     => $pipeline?->id,
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'job_type'        => $data['job_type'] ?? 'pipeline',
            'frequency'       => $data['frequency'] ?? 'daily',
            'cron_expression' => $data['cron_expression'] ?? null,
            'run_at'          => $data['run_at'] ?? null,
            'next_run_at'     => $data['next_run_at'] ?? null,
            'status'          => $data['status'] ?? 'active',
            'payload'         => $data['payload'] ?? null,
            'meta'            => $data['meta'] ?? null,
        ]);

        $this->audit($request, 'create_scheduled_job', 'scheduled_job', $job->id, 'low',
            "name={$job->name}, frequency={$job->frequency}", "id={$job->id}");

        $job->load('pipeline:id,name,status');

        return response()->json(['data' => $this->format($job)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $job = ScheduledJob::where('user_id', $this->apiUser($request)->id)
            ->with('pipeline:id,name,status')
            ->findOrFail($id);

        return response()->json(['data' => $this->format($job)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'description'     => 'sometimes|nullable|string|max:5000',
            'job_type'        => 'sometimes|string|max:100',
            'pipeline_id'     => 'sometimes|nullable|integer',
            'frequency'       => 'sometimes|in:' . implode(',', self::FREQUENCIES),
            'cron_expression' => 'sometimes|nullable|string|max:255',
            'run_at'          => 'sometimes|nullable|date',
            'next_run_at'     => 'sometimes|nullable|date',
            'status'          => 'sometimes|in:' . implode(',', self::STATUSES),
            'payload'         => 'sometimes|nullable|array',
            'meta'            => 'sometimes|nullable|array',
        ]);

        $user = $this->apiUser($request);
        $job  = ScheduledJob::where('user_id', $user->id)->findOrFail($id);

        if (array_key_exists('pipeline_id', $data)) {
            $job->pipeline_id = $this->resolvePipeline($user->id, $data['pipeline_id'])?->id;
        }
        foreach (['name', 'description', 'job_type', 'frequency', 'cron_expression', 'run_at', 'next_run_at', 'status', 'payload', 'meta'] as $field) {
            if (array_key_exists($field, $data)) {
                $job->{$field} = $data[$field];
            }
        }

        $job->save();

        $this->audit($request, 'update_scheduled_job', 'scheduled_job', $job->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$job->id}");

        $job->load('pipeline:id,name,status');

        return response()->json(['data' => $this->format($job)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $job = ScheduledJob::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $job->delete();

        $this->audit($request, 'delete_scheduled_job', 'scheduled_job', $id, 'low', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Manually trigger a scheduled job. Stamps the run counters; if the job is
     * linked to a pipeline, a PipelineRun is recorded (trigger_source=scheduled).
     * Like the rest of the scheduler surface this only records state — it does
     * not execute job logic inline. Paused jobs cannot be run.
     */
    public function run(Request $request, int $id): JsonResponse
    {
        $user = $this->apiUser($request);
        $job  = ScheduledJob::where('user_id', $user->id)->with('pipeline')->findOrFail($id);

        if ($job->status !== 'active') {
            return response()->json([
                'error'  => "Scheduled job cannot be run while status is '{$job->status}'. Only active jobs can run.",
                'status' => $job->status,
            ], 422);
        }

        $run = null;
        if ($job->pipeline && $job->pipeline->status === 'active') {
            $run = PipelineRun::create([
                'user_id'        => $user->id,
                'tenant_id'      => $user->tenant_id,
                'pipeline_id'    => $job->pipeline_id,
                'status'         => 'pending',
                'trigger_source' => 'scheduled',
                'input'          => $job->payload,
                'started_at'     => now(),
            ]);

            $job->pipeline->forceFill([
                'last_run_at' => now(),
                'run_count'   => $job->pipeline->run_count + 1,
            ])->save();
        }

        $job->forceFill([
            'last_run_at' => now(),
            'run_count'   => $job->run_count + 1,
        ])->save();

        $this->audit($request, 'run_scheduled_job', 'scheduled_job', $job->id, 'medium',
            "id={$job->id}", $run ? "run_id={$run->id}" : 'no_pipeline_linked');

        $job->load('pipeline:id,name,status');

        return response()->json([
            'data'    => $this->format($job),
            'run_id'  => $run?->id,
        ]);
    }

    private function resolvePipeline(int $userId, ?int $pipelineId): ?Pipeline
    {
        if (empty($pipelineId)) {
            return null;
        }

        return Pipeline::where('user_id', $userId)->findOrFail($pipelineId);
    }

    public function format(ScheduledJob $j): array
    {
        return [
            'id'              => $j->id,
            'name'            => $j->name,
            'description'     => $j->description,
            'job_type'        => $j->job_type,
            'pipeline_id'     => $j->pipeline_id,
            'frequency'       => $j->frequency,
            'cron_expression' => $j->cron_expression,
            'run_at'          => $j->run_at?->toISOString(),
            'status'          => $j->status,
            'payload'         => $j->payload,
            'meta'            => $j->meta,
            'run_count'       => $j->run_count,
            'last_run_at'     => $j->last_run_at?->toISOString(),
            'next_run_at'     => $j->next_run_at?->toISOString(),
            'pipeline'        => $j->relationLoaded('pipeline') ? $j->pipeline?->only(['id', 'name', 'status']) : null,
            'created_at'      => $j->created_at?->toISOString(),
            'updated_at'      => $j->updated_at?->toISOString(),
        ];
    }
}
