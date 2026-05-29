<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\Opportunity;
use App\Models\TimelineEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OpportunityController extends GptController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q'               => 'nullable|string|max:255',
            'type'            => ['nullable', Rule::in(['job', 'scholarship', 'research', 'grant', 'networking'])],
            'status'          => 'nullable|string',
            'priority'        => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'deadline_before' => 'nullable|date',
            'deadline_after'  => 'nullable|date',
            'has_contact'     => 'nullable|boolean',
            'limit'           => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 20), 100);

        $query = Opportunity::where('user_id', $user->id)->withCount('contacts');

        if ($q = $request->input('q')) {
            $query->where(function ($q2) use ($q) {
                $q2->where('title', 'like', "%{$q}%")
                   ->orWhere('organization', 'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%");
            });
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }
        if ($before = $request->input('deadline_before')) {
            $query->whereDate('deadline', '<=', $before);
        }
        if ($after = $request->input('deadline_after')) {
            $query->whereDate('deadline', '>=', $after);
        }
        if ($request->boolean('has_contact')) {
            $query->has('contacts');
        }

        $opportunities = $query->orderByDesc('updated_at')->limit($limit)->get();

        return response()->json([
            'data'  => $opportunities->map(fn ($o) => $this->format($o)),
            'count' => $opportunities->count(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $opp = Opportunity::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        return response()->json(['data' => $this->format($opp, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'type'         => ['required', Rule::in(['job', 'scholarship', 'research', 'grant', 'networking'])],
            'organization' => 'required|string|max:255',
            'description'  => 'nullable|string|max:5000',
            'url'          => 'nullable|url|max:2048',
            'status'       => ['nullable', Rule::in(['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed'])],
            'priority'     => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'deadline'     => 'nullable|date',
            'notes'        => 'nullable|string|max:5000',
        ]);

        $user = $this->apiUser($request);

        // Deduplication by title + organization + URL
        $existing = Opportunity::where('user_id', $user->id)
            ->where('title', $data['title'])
            ->where('organization', $data['organization'])
            ->when(! empty($data['url']), fn ($q) => $q->where('url', $data['url']))
            ->first();

        if ($existing) {
            return response()->json([
                'data'      => $this->format($existing),
                'duplicate' => true,
                'message'   => 'Existing opportunity returned. No duplicate created.',
            ], 200);
        }

        $opp = Opportunity::create([
            'user_id'         => $user->id,
            'tenant_id'       => $user->tenant_id,
            'title'           => $data['title'],
            'type'            => $data['type'],
            'organization'    => $data['organization'],
            'description'     => $data['description'] ?? null,
            'url'             => $data['url'] ?? null,
            'status'          => $data['status'] ?? 'draft',
            'priority'        => $data['priority'] ?? 'medium',
            'deadline'        => $data['deadline'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'last_activity_at' => now(),
        ]);

        TimelineEvent::create([
            'user_id'          => $user->id,
            'tenant_id'        => $user->tenant_id,
            'timelineable_type' => Opportunity::class,
            'timelineable_id'   => $opp->id,
            'event_type'       => 'created',
            'description'      => "Opportunity created via AI integration ({$this->apiClient($request)->source_type}).",
        ]);

        $this->audit($request, 'create_opportunity', 'opportunity', $opp->id, 'low',
            "title={$opp->title}, org={$opp->organization}",
            "id={$opp->id}",
        );

        return response()->json(['data' => $this->format($opp), 'duplicate' => false], 201);
    }

    public function addNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'note' => 'required|string|max:5000',
        ]);

        $user = $this->apiUser($request);
        $opp  = Opportunity::where('user_id', $user->id)->findOrFail($id);

        $opp->notes = ($opp->notes ? $opp->notes . "\n\n" : '') . '[AI] ' . $data['note'];
        $opp->save();

        $this->audit($request, 'add_note', 'opportunity', $opp->id, 'low', substr($data['note'], 0, 200));

        return response()->json(['message' => 'Note added.', 'data' => $this->format($opp)]);
    }

    private function format(Opportunity $o, bool $full = false): array
    {
        $base = [
            'id'           => $o->id,
            'title'        => $o->title,
            'type'         => $o->type,
            'organization' => $o->organization,
            'status'       => $o->status,
            'priority'     => $o->priority,
            'deadline'     => $o->deadline?->toDateString(),
            'url'          => $o->url,
            'created_at'   => $o->created_at?->toISOString(),
            'updated_at'   => $o->updated_at?->toISOString(),
        ];

        if ($full) {
            $base['description'] = $o->description;
            $base['notes']       = $o->notes;
        }

        return $base;
    }
}
