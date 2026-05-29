<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\TimelineEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IngestionController extends GptController
{
    /**
     * Bulk ingest opportunities from an external source (n8n, scraper, etc.).
     * Each item is deduplicated by title+organization+url before insertion.
     */
    public function opportunities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                  => 'required|array|min:1|max:50',
            'items.*.title'          => 'required|string|max:255',
            'items.*.type'           => ['required', Rule::in(['job', 'scholarship', 'research', 'grant', 'networking'])],
            'items.*.organization'   => 'required|string|max:255',
            'items.*.description'    => 'nullable|string|max:10000',
            'items.*.url'            => 'nullable|url|max:2048',
            'items.*.deadline'       => 'nullable|date',
            'items.*.priority'       => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'items.*.notes'          => 'nullable|string|max:2000',
        ]);

        $user    = $this->apiUser($request);
        $client  = $this->apiClient($request);
        $created = [];
        $dupes   = [];

        foreach ($data['items'] as $item) {
            $existing = Opportunity::where('user_id', $user->id)
                ->where('title', $item['title'])
                ->where('organization', $item['organization'])
                ->when(! empty($item['url']), fn ($q) => $q->where('url', $item['url']))
                ->first();

            if ($existing) {
                $dupes[] = ['id' => $existing->id, 'title' => $existing->title];
                continue;
            }

            $opp = Opportunity::create([
                'user_id'          => $user->id,
                'tenant_id'        => $user->tenant_id,
                'title'            => $item['title'],
                'type'             => $item['type'],
                'organization'     => $item['organization'],
                'description'      => $item['description'] ?? null,
                'url'              => $item['url'] ?? null,
                'status'           => 'draft',
                'priority'         => $item['priority'] ?? 'medium',
                'deadline'         => $item['deadline'] ?? null,
                'notes'            => $item['notes'] ?? null,
                'last_activity_at' => now(),
            ]);

            TimelineEvent::create([
                'user_id'           => $user->id,
                'tenant_id'         => $user->tenant_id,
                'timelineable_type' => Opportunity::class,
                'timelineable_id'   => $opp->id,
                'event_type'        => 'created',
                'description'       => "Opportunity ingested via {$client->source_type} ({$client->name}).",
            ]);

            $created[] = ['id' => $opp->id, 'title' => $opp->title];
        }

        $this->audit($request, 'ingest_opportunities', 'opportunity', null, 'medium',
            'count=' . count($data['items']),
            'created=' . count($created) . ', dupes=' . count($dupes),
        );

        return response()->json([
            'created'    => $created,
            'duplicates' => $dupes,
            'summary'    => ['created' => count($created), 'duplicates' => count($dupes)],
        ], 201);
    }

    /**
     * Bulk ingest contacts from an external source.
     */
    public function contacts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                  => 'required|array|min:1|max:50',
            'items.*.first_name'     => 'required|string|max:100',
            'items.*.last_name'      => 'nullable|string|max:100',
            'items.*.email'          => 'nullable|email|max:255',
            'items.*.company'        => 'nullable|string|max:255',
            'items.*.job_title'      => 'nullable|string|max:255',
            'items.*.phone'          => 'nullable|string|max:50',
            'items.*.linkedin_url'   => 'nullable|url|max:2048',
            'items.*.notes'          => 'nullable|string|max:2000',
        ]);

        $user    = $this->apiUser($request);
        $client  = $this->apiClient($request);
        $created = [];
        $dupes   = [];

        foreach ($data['items'] as $item) {
            if (! empty($item['email'])) {
                $existing = Contact::where('user_id', $user->id)
                    ->where('email', strtolower($item['email']))
                    ->first();

                if ($existing) {
                    $dupes[] = ['id' => $existing->id, 'email' => $existing->email];
                    continue;
                }
            }

            $contact = Contact::create([
                'user_id'      => $user->id,
                'tenant_id'    => $user->tenant_id,
                'first_name'   => $item['first_name'],
                'last_name'    => $item['last_name'] ?? null,
                'email'        => isset($item['email']) ? strtolower($item['email']) : null,
                'company'      => $item['company'] ?? null,
                'job_title'    => $item['job_title'] ?? null,
                'phone'        => $item['phone'] ?? null,
                'linkedin_url' => $item['linkedin_url'] ?? null,
                'notes'        => $item['notes'] ?? null,
                'status'       => 'active',
                'source'       => $client->source_type,
            ]);

            $created[] = ['id' => $contact->id, 'email' => $contact->email];
        }

        $this->audit($request, 'ingest_contacts', 'contact', null, 'medium',
            'count=' . count($data['items']),
            'created=' . count($created) . ', dupes=' . count($dupes),
        );

        return response()->json([
            'created'    => $created,
            'duplicates' => $dupes,
            'summary'    => ['created' => count($created), 'duplicates' => count($dupes)],
        ], 201);
    }
}
