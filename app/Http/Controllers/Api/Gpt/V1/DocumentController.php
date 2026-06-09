<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\Contact;
use App\Models\Document;
use App\Models\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentController extends GptController
{
    public function index(Request $request): JsonResponse
    {
        $user  = $this->apiUser($request);
        $query = Document::where('user_id', $user->id);

        if ($request->filled('opportunity_id')) {
            $query->where('opportunity_id', (int) $request->query('opportunity_id'));
        }
        if ($request->filled('contact_id')) {
            $query->where('contact_id', (int) $request->query('contact_id'));
        }
        if ($request->filled('document_type')) {
            $query->where('document_type', $request->query('document_type'));
        }

        $docs = $query->orderByDesc('created_at')->limit(50)->get();

        return response()->json([
            'data'  => $docs->map(fn($d) => $this->format($d))->values(),
            'count' => $docs->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:500',
            'public_url'     => 'required|url|max:2048',
            'mime_type'      => ['required', 'string', Rule::in(Document::ALLOWED_MIME_TYPES)],
            'document_type'  => ['nullable', Rule::in(Document::DOCUMENT_TYPES)],
            'description'    => 'nullable|string|max:2000',
            'opportunity_id' => 'nullable|integer',
            'contact_id'     => 'nullable|integer',
        ]);

        $user = $this->apiUser($request);

        if (isset($data['opportunity_id'])) {
            abort_unless(
                Opportunity::where('user_id', $user->id)->where('id', $data['opportunity_id'])->exists(),
                404, 'Opportunity not found.'
            );
        }
        if (isset($data['contact_id'])) {
            abort_unless(
                Contact::where('user_id', $user->id)->where('id', $data['contact_id'])->exists(),
                404, 'Contact not found.'
            );
        }

        $doc = Document::create([
            'tenant_id'      => $user->tenant_id,
            'user_id'        => $user->id,
            'opportunity_id' => $data['opportunity_id'] ?? null,
            'contact_id'     => $data['contact_id'] ?? null,
            'name'           => $data['name'],
            'public_url'     => $data['public_url'],
            'mime_type'      => $data['mime_type'],
            'document_type'  => $data['document_type'] ?? 'other',
            'description'    => $data['description'] ?? null,
        ]);

        $this->audit($request, 'create_document', 'document', $doc->id, 'low',
            "name={$doc->name}", "id={$doc->id}");

        return response()->json(['data' => $this->format($doc), 'message' => 'Document created.'], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $doc = Document::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        return response()->json(['data' => $this->format($doc)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $doc = Document::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        $this->audit($request, 'delete_document', 'document', $doc->id, 'medium',
            "id={$doc->id}", 'deleted');

        $doc->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    // ── Nested: /opportunities/{id}/documents ────────────────────────────────

    public function indexForOpportunity(Request $request, int $opportunityId): JsonResponse
    {
        $user = $this->apiUser($request);
        $opp  = Opportunity::where('user_id', $user->id)->findOrFail($opportunityId);

        $docs = Document::where('user_id', $user->id)
            ->where('opportunity_id', $opp->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data'  => $docs->map(fn($d) => $this->format($d))->values(),
            'count' => $docs->count(),
        ]);
    }

    public function storeForOpportunity(Request $request, int $opportunityId): JsonResponse
    {
        $user = $this->apiUser($request);
        $opp  = Opportunity::where('user_id', $user->id)->findOrFail($opportunityId);

        $data = $request->validate([
            'name'          => 'required|string|max:500',
            'public_url'    => 'required|url|max:2048',
            'mime_type'     => ['required', 'string', Rule::in(Document::ALLOWED_MIME_TYPES)],
            'document_type' => ['nullable', Rule::in(Document::DOCUMENT_TYPES)],
            'description'   => 'nullable|string|max:2000',
        ]);

        $doc = Document::create([
            'tenant_id'      => $user->tenant_id,
            'user_id'        => $user->id,
            'opportunity_id' => $opp->id,
            'name'           => $data['name'],
            'public_url'     => $data['public_url'],
            'mime_type'      => $data['mime_type'],
            'document_type'  => $data['document_type'] ?? 'other',
            'description'    => $data['description'] ?? null,
        ]);

        $this->audit($request, 'create_document', 'document', $doc->id, 'low',
            "name={$doc->name}, opportunity_id={$opp->id}", "id={$doc->id}");

        return response()->json(['data' => $this->format($doc), 'message' => 'Document added to opportunity.'], 201);
    }

    // ── Nested: /contacts/{id}/documents ────────────────────────────────────

    public function indexForContact(Request $request, int $contactId): JsonResponse
    {
        $user    = $this->apiUser($request);
        $contact = Contact::where('user_id', $user->id)->findOrFail($contactId);

        $docs = Document::where('user_id', $user->id)
            ->where('contact_id', $contact->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data'  => $docs->map(fn($d) => $this->format($d))->values(),
            'count' => $docs->count(),
        ]);
    }

    public function storeForContact(Request $request, int $contactId): JsonResponse
    {
        $user    = $this->apiUser($request);
        $contact = Contact::where('user_id', $user->id)->findOrFail($contactId);

        $data = $request->validate([
            'name'          => 'required|string|max:500',
            'public_url'    => 'required|url|max:2048',
            'mime_type'     => ['required', 'string', Rule::in(Document::ALLOWED_MIME_TYPES)],
            'document_type' => ['nullable', Rule::in(Document::DOCUMENT_TYPES)],
            'description'   => 'nullable|string|max:2000',
        ]);

        $doc = Document::create([
            'tenant_id'     => $user->tenant_id,
            'user_id'       => $user->id,
            'contact_id'    => $contact->id,
            'name'          => $data['name'],
            'public_url'    => $data['public_url'],
            'mime_type'     => $data['mime_type'],
            'document_type' => $data['document_type'] ?? 'other',
            'description'   => $data['description'] ?? null,
        ]);

        $this->audit($request, 'create_document', 'document', $doc->id, 'low',
            "name={$doc->name}, contact_id={$contact->id}", "id={$doc->id}");

        return response()->json(['data' => $this->format($doc), 'message' => 'Document added to contact.'], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function format(Document $d): array
    {
        return [
            'id'             => $d->id,
            'name'           => $d->name,
            'description'    => $d->description,
            'document_type'  => $d->document_type,
            'public_url'     => $d->public_url,
            'file_name'      => $d->file_name,
            'file_size'      => $d->file_size,
            'mime_type'      => $d->mime_type,
            'opportunity_id' => $d->opportunity_id,
            'contact_id'     => $d->contact_id,
            'created_at'     => $d->created_at?->toISOString(),
        ];
    }
}
