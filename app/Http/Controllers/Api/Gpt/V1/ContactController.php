<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends GptController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q'            => 'nullable|string|max:255',
            'email'        => 'nullable|email',
            'organization' => 'nullable|string|max:255',
            'status'       => 'nullable|string',
            'limit'        => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 20), 100);

        $query = Contact::where('user_id', $user->id);

        if ($q = $request->input('q')) {
            $query->where(function ($q2) use ($q) {
                $q2->where('first_name', 'like', "%{$q}%")
                   ->orWhere('last_name', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%")
                   ->orWhere('company', 'like', "%{$q}%");
            });
        }
        if ($email = $request->input('email')) {
            $query->where('email', $email);
        }
        if ($org = $request->input('organization')) {
            $query->where('company', 'like', "%{$org}%");
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $contacts = $query->orderByDesc('updated_at')->limit($limit)->get();

        return response()->json([
            'data'  => $contacts->map(fn ($c) => $this->format($c)),
            'count' => $contacts->count(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $contact = Contact::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        return response()->json(['data' => $this->format($contact, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'   => 'required_without:full_name|nullable|string|max:100',
            'full_name'    => 'required_without:first_name|nullable|string|max:200',
            'last_name'    => 'nullable|string|max:100',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'company'      => 'nullable|string|max:255',
            'job_title'    => 'nullable|string|max:255',
            'linkedin_url' => 'nullable|url|max:2048',
            'notes'        => 'nullable|string|max:5000',
            'status'       => ['nullable', Rule::in(['active', 'suppressed', 'bounced'])],
        ]);

        $user = $this->apiUser($request);

        // Parse full_name if provided
        if (empty($data['first_name']) && ! empty($data['full_name'])) {
            $parts = explode(' ', $data['full_name'], 2);
            $data['first_name'] = $parts[0];
            $data['last_name']  = $parts[1] ?? null;
        }

        // Deduplicate by email
        if (! empty($data['email'])) {
            $existing = Contact::where('user_id', $user->id)
                ->where('email', strtolower($data['email']))
                ->first();

            if ($existing) {
                return response()->json([
                    'data'      => $this->format($existing),
                    'duplicate' => true,
                    'message'   => 'Existing contact returned. No duplicate created.',
                ], 200);
            }
        }

        $contact = Contact::create([
            'user_id'      => $user->id,
            'tenant_id'    => $user->tenant_id,
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'] ?? null,
            'email'        => isset($data['email']) ? strtolower($data['email']) : null,
            'phone'        => $data['phone'] ?? null,
            'company'      => $data['company'] ?? null,
            'job_title'    => $data['job_title'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'notes'        => $data['notes'] ?? null,
            'status'       => $data['status'] ?? 'active',
            'source'       => $this->apiClient($request)->source_type,
        ]);

        $this->audit($request, 'create_contact', 'contact', $contact->id, 'low',
            "email={$contact->email}, company={$contact->company}",
            "id={$contact->id}",
        );

        return response()->json(['data' => $this->format($contact), 'duplicate' => false], 201);
    }

    public function addNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'note' => 'required|string|max:5000',
        ]);

        $user    = $this->apiUser($request);
        $contact = Contact::where('user_id', $user->id)->findOrFail($id);

        $contact->notes = ($contact->notes ? $contact->notes . "\n\n" : '') . '[AI] ' . $data['note'];
        $contact->save();

        $this->audit($request, 'add_note', 'contact', $contact->id, 'low', substr($data['note'], 0, 200));

        return response()->json(['message' => 'Note added.', 'data' => $this->format($contact)]);
    }

    private function format(Contact $c, bool $full = false): array
    {
        $base = [
            'id'           => $c->id,
            'full_name'    => $c->full_name,
            'first_name'   => $c->first_name,
            'last_name'    => $c->last_name,
            'email'        => $c->email,
            'company'      => $c->company,
            'job_title'    => $c->job_title,
            'status'       => $c->status,
            'created_at'   => $c->created_at?->toISOString(),
        ];

        if ($full) {
            $base['phone']        = $c->phone;
            $base['linkedin_url'] = $c->linkedin_url;
            $base['notes']        = $c->notes;
        }

        return $base;
    }
}
