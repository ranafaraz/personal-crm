<?php

namespace App\Http\Controllers\Api\Gpt\V1\Proposal;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalController extends GptController
{
    private const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'         => 'nullable|string|in:' . implode(',', self::STATUSES),
            'contact_id'     => 'nullable|integer',
            'opportunity_id' => 'nullable|integer',
            'search'         => 'nullable|string|max:200',
            'limit'          => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = Proposal::where('user_id', $user->id)->with(['contact', 'opportunity']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($contactId = $request->input('contact_id')) {
            $query->where('contact_id', $contactId);
        }
        if ($opportunityId = $request->input('opportunity_id')) {
            $query->where('opportunity_id', $opportunityId);
        }
        if ($search = $request->input('search')) {
            $query->where('title', 'like', '%' . $search . '%');
        }

        $proposals = $query->orderByDesc('id')->limit($limit)->get();

        return $this->listResponse($proposals->map(fn ($p) => $this->format($p))->values(), $limit);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'          => 'required|string|max:500',
            'version'        => 'nullable|string|max:100',
            'contact_id'     => 'nullable|integer',
            'opportunity_id' => 'nullable|integer',
            'status'         => 'nullable|in:' . implode(',', self::STATUSES),
            'amount'         => 'nullable|numeric|min:0',
            'currency'       => 'nullable|string|size:3',
            'body'           => 'nullable|string|max:100000',
            'url'            => 'nullable|url|max:2000',
            'valid_until'    => 'nullable|date',
            'meta'           => 'nullable|array',
        ]);

        $user = $this->apiUser($request);

        // Validate ownership of linked entities before associating.
        $contact     = $this->resolveContact($user->id, $data['contact_id'] ?? null);
        $opportunity = $this->resolveOpportunity($user->id, $data['opportunity_id'] ?? null);

        $proposal = Proposal::create([
            'user_id'        => $user->id,
            'tenant_id'      => $user->tenant_id,
            'contact_id'     => $contact?->id,
            'opportunity_id' => $opportunity?->id,
            'title'          => $data['title'],
            'version'        => $data['version'] ?? null,
            'status'         => $data['status'] ?? 'draft',
            'amount'         => $data['amount'] ?? null,
            'currency'       => strtoupper($data['currency'] ?? 'USD'),
            'body'           => $data['body'] ?? null,
            'url'            => $data['url'] ?? null,
            'valid_until'    => $data['valid_until'] ?? null,
            'meta'           => $data['meta'] ?? null,
        ]);

        $this->audit($request, 'create_proposal', 'proposal', $proposal->id, 'low',
            "title={$proposal->title}, status={$proposal->status}, amount=" . ($proposal->amount ?? 'null'),
            "id={$proposal->id}");

        $proposal->load(['contact', 'opportunity']);

        return response()->json(['data' => $this->format($proposal)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $proposal = Proposal::where('user_id', $this->apiUser($request)->id)
            ->with(['contact', 'opportunity'])
            ->findOrFail($id);

        return response()->json(['data' => $this->format($proposal)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title'          => 'sometimes|string|max:500',
            'version'        => 'sometimes|nullable|string|max:100',
            'contact_id'     => 'sometimes|nullable|integer',
            'opportunity_id' => 'sometimes|nullable|integer',
            'status'         => 'sometimes|in:' . implode(',', self::STATUSES),
            'amount'         => 'sometimes|nullable|numeric|min:0',
            'currency'       => 'sometimes|string|size:3',
            'body'           => 'sometimes|nullable|string|max:100000',
            'url'            => 'sometimes|nullable|url|max:2000',
            'valid_until'    => 'sometimes|nullable|date',
            'meta'           => 'sometimes|nullable|array',
        ]);

        $user     = $this->apiUser($request);
        $proposal = Proposal::where('user_id', $user->id)->findOrFail($id);

        if (array_key_exists('contact_id', $data)) {
            $proposal->contact_id = $this->resolveContact($user->id, $data['contact_id'])?->id;
        }
        if (array_key_exists('opportunity_id', $data)) {
            $proposal->opportunity_id = $this->resolveOpportunity($user->id, $data['opportunity_id'])?->id;
        }
        if (array_key_exists('currency', $data)) {
            $proposal->currency = strtoupper($data['currency']);
        }
        foreach (['title', 'version', 'status', 'amount', 'body', 'url', 'valid_until', 'meta'] as $field) {
            if (array_key_exists($field, $data)) {
                $proposal->{$field} = $data[$field];
            }
        }

        // Stamp responded_at when moving into a terminal response state.
        if (array_key_exists('status', $data) && in_array($data['status'], ['accepted', 'rejected'], true) && ! $proposal->responded_at) {
            $proposal->responded_at = now();
        }

        $proposal->save();

        $this->audit($request, 'update_proposal', 'proposal', $proposal->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$proposal->id}");

        $proposal->load(['contact', 'opportunity']);

        return response()->json(['data' => $this->format($proposal)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $proposal = Proposal::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $proposal->delete();

        $this->audit($request, 'delete_proposal', 'proposal', $id, 'low', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Mark a proposal as sent. Records the send time. This only updates CRM
     * state — it does not transmit the proposal to the client.
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $user     = $this->apiUser($request);
        $proposal = Proposal::where('user_id', $user->id)->findOrFail($id);

        if ($proposal->status !== 'draft') {
            return response()->json([
                'error'  => "Proposal cannot be sent from status '{$proposal->status}'. Only draft proposals can be sent.",
                'status' => $proposal->status,
            ], 422);
        }

        $proposal->status  = 'sent';
        $proposal->sent_at = now();
        $proposal->save();

        $this->audit($request, 'send_proposal', 'proposal', $proposal->id, 'medium',
            "id={$proposal->id}", "sent_at={$proposal->sent_at}");

        $proposal->load(['contact', 'opportunity']);

        return response()->json(['data' => $this->format($proposal)]);
    }

    private function resolveContact(int $userId, ?int $contactId): ?Contact
    {
        if (empty($contactId)) {
            return null;
        }

        return Contact::where('user_id', $userId)->findOrFail($contactId);
    }

    private function resolveOpportunity(int $userId, ?int $opportunityId): ?Opportunity
    {
        if (empty($opportunityId)) {
            return null;
        }

        return Opportunity::where('user_id', $userId)->findOrFail($opportunityId);
    }

    public function format(Proposal $p): array
    {
        return [
            'id'             => $p->id,
            'title'          => $p->title,
            'version'        => $p->version,
            'status'         => $p->status,
            'amount'         => $p->amount,
            'currency'       => $p->currency,
            'body'           => $p->body,
            'url'            => $p->url,
            'valid_until'    => $p->valid_until?->toDateString(),
            'sent_at'        => $p->sent_at?->toISOString(),
            'responded_at'   => $p->responded_at?->toISOString(),
            'meta'           => $p->meta,
            'contact_id'     => $p->contact_id,
            'opportunity_id' => $p->opportunity_id,
            'contact'        => $p->relationLoaded('contact') ? $p->contact?->only(['id', 'first_name', 'last_name', 'email']) : null,
            'opportunity'    => $p->relationLoaded('opportunity') ? $p->opportunity?->only(['id', 'title', 'status']) : null,
            'created_at'     => $p->created_at?->toISOString(),
            'updated_at'     => $p->updated_at?->toISOString(),
        ];
    }
}
