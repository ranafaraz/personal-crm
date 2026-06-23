<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\Opportunity;
use App\Models\SuppressionList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Bulk CREATE operations (up to 20 items per request).
 * POST /api/gpt/v1/bulk/opportunities
 * POST /api/gpt/v1/bulk/contacts
 * POST /api/gpt/v1/bulk/drafts
 * Scope: bulk:write
 */
class BulkCreateController extends GptController
{
    public function opportunities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opportunities'         => 'required|array|min:1|max:20',
            'opportunities.*.title' => 'required|string|max:255',
            'opportunities.*.company'    => 'required|string|max:255',
            'opportunities.*.type'       => ['nullable', Rule::in(['job', 'scholarship', 'research', 'grant', 'networking'])],
            'opportunities.*.status'     => ['nullable', Rule::in(['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed'])],
            'opportunities.*.priority'   => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'opportunities.*.apply_url'  => 'nullable|url|max:2048',
            'opportunities.*.notes'      => 'nullable|string|max:5000',
            'opportunities.*.tags'       => 'nullable|array|max:10',
            'opportunities.*.tags.*'     => 'string|max:100',
        ]);

        $user    = $this->apiUser($request);
        $created = [];
        $skipped = [];
        $errors  = [];

        foreach ($data['opportunities'] as $index => $item) {
            // Dedup: same title + company already exists
            $existing = Opportunity::where('user_id', $user->id)
                ->where('title', $item['title'])
                ->where('organization', $item['company'])
                ->first();

            if ($existing) {
                $skipped[] = ['title' => $item['title'], 'company' => $item['company'], 'reason' => 'duplicate', 'id' => $existing->id];
                continue;
            }

            try {
                $opp = Opportunity::create([
                    'user_id'      => $user->id,
                    'tenant_id'    => $user->tenant_id,
                    'title'        => $item['title'],
                    'organization' => $item['company'],
                    'type'         => $item['type'] ?? 'job',
                    'status'       => $item['status'] ?? 'draft',
                    'priority'     => $item['priority'] ?? 'medium',
                    'url'          => $item['apply_url'] ?? null,
                    'notes'        => $item['notes'] ?? null,
                ]);

                // Apply tags if provided
                if (! empty($item['tags'])) {
                    $this->applyTags($user->id, $user->tenant_id, $opp, $item['tags']);
                }

                $created[] = ['id' => $opp->id, 'title' => $opp->title, 'company' => $opp->organization];
            } catch (\Throwable $e) {
                $errors[] = ['index' => $index, 'title' => $item['title'], 'error' => $e->getMessage()];
            }
        }

        $this->audit($request, 'bulk_create_opportunities', 'opportunity', null, 'medium',
            "requested=" . count($data['opportunities']),
            "created=" . count($created) . ",skipped=" . count($skipped));

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ], 201);
    }

    public function contacts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contacts'               => 'required|array|min:1|max:20',
            'contacts.*.email'       => 'required|email|max:255',
            'contacts.*.first_name'  => 'nullable|string|max:100',
            'contacts.*.last_name'   => 'nullable|string|max:100',
            'contacts.*.company'     => 'nullable|string|max:255',
            'contacts.*.job_title'   => 'nullable|string|max:255',
            'contacts.*.phone'       => 'nullable|string|max:50',
            'contacts.*.linkedin_url'=> 'nullable|url|max:2048',
            'contacts.*.notes'       => 'nullable|string|max:5000',
            'contacts.*.status'      => ['nullable', Rule::in(['active', 'inactive', 'suppressed', 'bounced'])],
        ]);

        $user    = $this->apiUser($request);
        $created = [];
        $skipped = [];
        $errors  = [];

        foreach ($data['contacts'] as $index => $item) {
            $existing = Contact::where('user_id', $user->id)->where('email', $item['email'])->first();

            if ($existing) {
                $skipped[] = ['email' => $item['email'], 'reason' => 'duplicate', 'id' => $existing->id];
                continue;
            }

            try {
                $contact = Contact::create([
                    'user_id'     => $user->id,
                    'tenant_id'   => $user->tenant_id,
                    'email'       => $item['email'],
                    'first_name'  => $item['first_name'] ?? '',
                    'last_name'   => $item['last_name'] ?? '',
                    'company'     => $item['company'] ?? null,
                    'job_title'   => $item['job_title'] ?? null,
                    'phone'       => $item['phone'] ?? null,
                    'linkedin_url'=> $item['linkedin_url'] ?? null,
                    'notes'       => $item['notes'] ?? null,
                    'status'      => $item['status'] ?? 'active',
                ]);

                $created[] = ['id' => $contact->id, 'email' => $contact->email, 'name' => trim($contact->first_name . ' ' . $contact->last_name)];
            } catch (\Throwable $e) {
                $errors[] = ['index' => $index, 'email' => $item['email'], 'error' => $e->getMessage()];
            }
        }

        $this->audit($request, 'bulk_create_contacts', 'contact', null, 'medium',
            "requested=" . count($data['contacts']),
            "created=" . count($created) . ",skipped=" . count($skipped));

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ], 201);
    }

    public function drafts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'drafts'                    => 'required|array|min:1|max:20',
            'drafts.*.contact_id'       => 'required|integer',
            'drafts.*.subject'          => 'required|string|max:500',
            'drafts.*.body'             => 'required|string|max:50000',
            'drafts.*.opportunity_id'   => 'nullable|integer',
        ]);

        $user    = $this->apiUser($request);
        $created = [];
        $errors  = [];

        $emailAccount = EmailAccount::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        foreach ($data['drafts'] as $index => $item) {
            $contact = Contact::where('user_id', $user->id)->find($item['contact_id']);
            if (! $contact) {
                $errors[] = ['index' => $index, 'error' => "contact_id {$item['contact_id']} not found"];
                continue;
            }

            if (empty($contact->email)) {
                $errors[] = ['index' => $index, 'error' => "contact {$item['contact_id']} has no email"];
                continue;
            }

            if (SuppressionList::isSuppressed($user->id, $contact->email)) {
                $errors[] = ['index' => $index, 'error' => "contact {$item['contact_id']} is suppressed"];
                continue;
            }

            $oppId = null;
            if (! empty($item['opportunity_id'])) {
                $opp = Opportunity::where('user_id', $user->id)->find($item['opportunity_id']);
                if (! $opp) {
                    $errors[] = ['index' => $index, 'error' => "opportunity_id {$item['opportunity_id']} not found"];
                    continue;
                }
                $oppId = $opp->id;
            }

            try {
                $draft = EmailMessage::create([
                    'user_id'          => $user->id,
                    'tenant_id'        => $user->tenant_id,
                    'email_account_id' => $emailAccount?->id,
                    'contact_id'       => $contact->id,
                    'opportunity_id'   => $oppId,
                    'subject'          => $item['subject'],
                    'body'             => $item['body'],
                    'to_email'         => $contact->email,
                    'to_name'          => trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')),
                    'status'           => 'draft',
                    'direction'        => 'outbound',
                ]);

                $created[] = ['id' => $draft->id, 'subject' => $draft->subject, 'to_email' => $draft->to_email];
            } catch (\Throwable $e) {
                $errors[] = ['index' => $index, 'error' => $e->getMessage()];
            }
        }

        $this->audit($request, 'bulk_create_drafts', 'email_message', null, 'medium',
            "requested=" . count($data['drafts']),
            "created=" . count($created));

        return response()->json([
            'created' => $created,
            'skipped' => [],
            'errors'  => $errors,
        ], 201);
    }

    private function applyTags(int $userId, ?int $tenantId, Opportunity $opp, array $tagNames): void
    {
        $tagIds = [];
        foreach ($tagNames as $name) {
            $slug  = \Illuminate\Support\Str::slug($name);
            $tag   = \App\Models\Tag::firstOrCreate(
                ['user_id' => $userId, 'slug' => $slug],
                ['name' => $name, 'color' => '#6366f1'],
            );
            $tagIds[] = $tag->id;
        }
        $opp->tags()->syncWithoutDetaching($tagIds);
    }
}
