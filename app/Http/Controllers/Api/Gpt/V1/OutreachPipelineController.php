<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\SuppressionList;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OutreachPipelineController extends GptController
{
    private const PIPELINE_TYPE_MAP = [
        'job_application'    => 'job',
        'networking_outreach'=> 'networking',
        'freelance_pitch'    => 'research',
        'research_contact'   => 'research',
        'grant_application'  => 'grant',
    ];

    /**
     * Execute a complete multi-step outreach pipeline in one API call.
     * Steps: contact upsert → opportunity create → draft create → follow-up → tags.
     * Scope: pipelines:execute
     */
    public function execute(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pipeline'              => ['required', Rule::in(array_keys(self::PIPELINE_TYPE_MAP))],
            'data.company_name'     => 'required|string|max:255',
            'data.role_title'       => 'required|string|max:255',
            'data.contact_email'    => 'required|email|max:255',
            'data.contact_name'     => 'nullable|string|max:255',
            'data.email_subject'    => 'required|string|max:500',
            'data.email_body'       => 'required|string|max:50000',
            'data.follow_up_days'   => 'nullable|integer|min:1|max:90',
            'data.tags'             => 'nullable|array|max:20',
            'data.tags.*'           => 'string|max:100',
            'data.apply_url'        => 'nullable|url|max:2048',
            'data.opportunity_status'=> ['nullable', Rule::in(['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed'])],
        ]);

        $user     = $this->apiUser($request);
        $pd       = $data['data'];
        $steps    = [];
        $errors   = [];

        // ── Step 1: Contact upsert ─────────────────────────────────────────
        $contact = Contact::where('user_id', $user->id)
            ->where('email', $pd['contact_email'])
            ->first();

        if (! $contact) {
            $nameParts = explode(' ', $pd['contact_name'] ?? '', 2);
            $contact   = Contact::create([
                'user_id'    => $user->id,
                'tenant_id'  => $user->tenant_id,
                'email'      => $pd['contact_email'],
                'first_name' => $nameParts[0] ?: 'Unknown',
                'last_name'  => $nameParts[1] ?? '',
                'company'    => $pd['company_name'],
                'status'     => 'active',
            ]);
            $steps[] = 'contact_created';
        } else {
            $steps[] = 'contact_upserted';
        }

        // ── Step 2: Opportunity create (dedup by title+org) ───────────────
        $opp = Opportunity::where('user_id', $user->id)
            ->where('title', $pd['role_title'])
            ->where('organization', $pd['company_name'])
            ->first();

        if (! $opp) {
            $opp = Opportunity::create([
                'user_id'      => $user->id,
                'tenant_id'    => $user->tenant_id,
                'title'        => $pd['role_title'],
                'organization' => $pd['company_name'],
                'type'         => self::PIPELINE_TYPE_MAP[$data['pipeline']],
                'status'       => $pd['opportunity_status'] ?? 'draft',
                'priority'     => 'medium',
                'url'          => $pd['apply_url'] ?? null,
            ]);
            $steps[] = 'opportunity_created';
        } else {
            $steps[] = 'opportunity_upserted';
        }

        // Link contact → opportunity
        $opp->contacts()->syncWithoutDetaching([$contact->id]);

        // ── Step 3: Email draft ────────────────────────────────────────────
        $draftId = null;
        $suppressed = in_array($contact->status, ['suppressed', 'bounced'], true)
            || SuppressionList::isSuppressed($user->id, $contact->email);

        if ($suppressed) {
            $errors[] = 'draft_skipped: contact is suppressed';
        } else {
            $emailAccount = EmailAccount::where('user_id', $user->id)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->first();

            $draft   = EmailMessage::create([
                'user_id'          => $user->id,
                'tenant_id'        => $user->tenant_id,
                'email_account_id' => $emailAccount?->id,
                'contact_id'       => $contact->id,
                'opportunity_id'   => $opp->id,
                'subject'          => $pd['email_subject'],
                'body'             => $pd['email_body'],
                'to_email'         => $contact->email,
                'to_name'          => trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')),
                'status'           => 'draft',
                'direction'        => 'outbound',
            ]);
            $draftId = $draft->id;
            $steps[] = 'draft_created';
        }

        // ── Step 4: Follow-up reminder ────────────────────────────────────
        $days      = (int) ($pd['follow_up_days'] ?? 7);
        $followup  = FollowUp::create([
            'user_id'        => $user->id,
            'tenant_id'      => $user->tenant_id,
            'contact_id'     => $contact->id,
            'opportunity_id' => $opp->id,
            'subject'        => 'Follow up: ' . $pd['email_subject'],
            'due_at'         => now()->addDays($days),
            'status'         => 'pending',
        ]);
        $steps[] = 'followup_scheduled';

        // ── Step 5: Tags on opportunity ───────────────────────────────────
        if (! empty($pd['tags'])) {
            $tagIds = [];
            foreach ($pd['tags'] as $tagName) {
                $slug  = Str::slug($tagName);
                $tag   = Tag::firstOrCreate(
                    ['user_id' => $user->id, 'slug' => $slug],
                    ['name' => $tagName, 'color' => '#6366f1'],
                );
                $tagIds[] = $tag->id;
            }
            $opp->tags()->syncWithoutDetaching($tagIds);
            $steps[] = 'tags_applied';
        }

        $this->audit($request, 'outreach_pipeline_execute', 'opportunity', $opp->id, 'medium',
            "pipeline={$data['pipeline']}, contact_id={$contact->id}",
            implode(',', $steps));

        return response()->json([
            'pipeline'        => $data['pipeline'],
            'steps_completed' => $steps,
            'contact_id'      => $contact->id,
            'opportunity_id'  => $opp->id,
            'draft_id'        => $draftId,
            'followup_id'     => $followup->id,
            'errors'          => $errors,
        ], 201);
    }
}
