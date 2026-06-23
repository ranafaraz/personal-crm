<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\Contact;
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
        $opp = Opportunity::where('user_id', $this->apiUser($request)->id)
            ->with('contacts')
            ->findOrFail($id);

        return response()->json(['data' => $this->format($opp, true)]);
    }

    public function linkContact(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'contact_id' => 'required|integer',
            'role'       => 'nullable|string|max:100',
        ]);

        $user    = $this->apiUser($request);
        $opp     = Opportunity::where('user_id', $user->id)->findOrFail($id);
        $contact = Contact::where('user_id', $user->id)->findOrFail($data['contact_id']);

        $opp->contacts()->syncWithoutDetaching([
            $contact->id => ['role' => $data['role'] ?? null],
        ]);

        $opp->load('contacts');

        $this->audit($request, 'link_contact', 'opportunity', $opp->id, 'low',
            "contact_id={$contact->id}, role=" . ($data['role'] ?? 'null'));

        return response()->json([
            'message'        => 'Contact linked to opportunity.',
            'contacts_count' => $opp->contacts->count(),
            'contacts'       => $opp->contacts->map(fn ($c) => $c->only(['id', 'first_name', 'last_name', 'email']))->values(),
        ]);
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
            'user_id'           => $user->id,
            'tenant_id'         => $user->tenant_id,
            'timelineable_type' => Opportunity::class,
            'timelineable_id'   => $opp->id,
            'event_type'        => 'created',
            'description'       => "Opportunity created via AI integration ({$this->apiClient($request)->source_type}).",
            'happened_at'       => now(),
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

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'organization' => 'sometimes|string|max:255',
            'description'  => 'sometimes|nullable|string|max:5000',
            'url'          => 'sometimes|nullable|url|max:2048',
            'status'       => ['sometimes', Rule::in(['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed'])],
            'priority'     => ['sometimes', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'deadline'     => 'sometimes|nullable|date',
            'notes'        => 'sometimes|nullable|string|max:5000',
        ]);

        $user = $this->apiUser($request);
        $opp  = Opportunity::where('user_id', $user->id)->findOrFail($id);

        if (empty($data)) {
            return response()->json(['error' => 'No updatable fields provided.'], 422);
        }

        $opp->fill($data);
        $opp->last_activity_at = now();
        $opp->save();

        $this->audit($request, 'update_opportunity', 'opportunity', $opp->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$opp->id}");

        $opp->load('contacts');

        return response()->json(['data' => $this->format($opp, true)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $opp = Opportunity::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $opp->delete();

        $this->audit($request, 'delete_opportunity', 'opportunity', $id, 'medium', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Check for a duplicate opportunity without creating one (5C).
     * GET /api/gpt/v1/opportunities/check-duplicate?company=Acme&role=Engineer
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $company = trim((string) $request->query('company', ''));
        $role    = trim((string) $request->query('role', ''));

        if ($company === '' || $role === '') {
            return response()->json(['error' => 'Both company and role query parameters are required.'], 422);
        }

        $user = $this->apiUser($request);

        $existing = Opportunity::where('user_id', $user->id)
            ->where('organization', $company)
            ->where('title', $role)
            ->withTrashed()
            ->first();

        return response()->json([
            'duplicate'    => $existing !== null,
            'deleted'      => $existing?->trashed() ?? false,
            'opportunity'  => $existing ? $this->format($existing) : null,
        ]);
    }

    private function format(Opportunity $o, bool $full = false): array
    {
        $contactsLoaded = $o->relationLoaded('contacts');

        $base = [
            'id'             => $o->id,
            'title'          => $o->title,
            'type'           => $o->type,
            'organization'   => $o->organization,
            'status'         => $o->status,
            'priority'       => $o->priority,
            'deadline'       => $o->deadline?->toDateString(),
            'url'            => $o->url,
            'contacts_count' => $contactsLoaded ? $o->contacts->count() : ($o->contacts_count ?? 0),
            'created_at'     => $o->created_at?->toISOString(),
            'updated_at'     => $o->updated_at?->toISOString(),
        ];

        if ($full) {
            $base['description'] = $o->description;
            $base['notes']       = $o->notes;
            $base['contacts']    = $contactsLoaded
                ? $o->contacts->map(fn ($c) => $c->only(['id', 'first_name', 'last_name', 'email']))->values()
                : [];
        }

        return $base;
    }
}
