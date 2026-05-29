<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\Contact;
use App\Models\EmailMessage;
use App\Models\Opportunity;
use App\Models\SuppressionList;
use App\Models\TimelineEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailDraftController extends GptController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $drafts = EmailMessage::where('user_id', $user->id)
            ->where('status', 'draft')
            ->where('direction', 'outbound')
            ->with(['contact', 'opportunity'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data'  => $drafts->map(fn ($d) => $this->format($d)),
            'count' => $drafts->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_id'     => 'required|integer',
            'opportunity_id' => 'nullable|integer',
            'subject'        => 'required|string|max:500',
            'body'           => 'required|string|max:50000',
            'draft_type'     => ['nullable', Rule::in(['initial_outreach', 'follow_up', 'thank_you', 'general'])],
            'tone'           => ['nullable', Rule::in(['professional', 'casual', 'formal'])],
            'requires_review' => 'nullable|boolean',
        ]);

        $user = $this->apiUser($request);

        // Verify contact ownership
        $contact = Contact::where('user_id', $user->id)->findOrFail($data['contact_id']);

        // Block suppressed contacts
        $suppressed = in_array($contact->status, ['suppressed', 'bounced'], true);
        if (! $suppressed && $contact->email) {
            $suppressed = SuppressionList::isSuppressed($user->id, $contact->email);
        }

        if ($suppressed) {
            $this->audit($request, 'create_draft_blocked', 'contact', $contact->id, 'medium',
                "contact_id={$contact->id}",
                'blocked: suppressed contact',
                'blocked',
            );
            return response()->json([
                'error'   => 'Cannot create draft for a suppressed, bounced, or unsubscribed contact.',
                'contact' => ['id' => $contact->id, 'status' => $contact->status],
            ], 422);
        }

        // Verify opportunity ownership if provided
        $opportunity = null;
        if (! empty($data['opportunity_id'])) {
            $opportunity = Opportunity::where('user_id', $user->id)->findOrFail($data['opportunity_id']);
        }

        $draft = EmailMessage::create([
            'user_id'        => $user->id,
            'tenant_id'      => $user->tenant_id,
            'contact_id'     => $contact->id,
            'opportunity_id' => $opportunity?->id,
            'subject'        => $data['subject'],
            'body'           => $data['body'],
            'to_email'       => $contact->email,
            'to_name'        => $contact->full_name,
            'status'         => 'draft',
            'direction'      => 'outbound',
            'is_follow_up'   => ($data['draft_type'] ?? '') === 'follow_up',
        ]);

        TimelineEvent::create([
            'user_id'           => $user->id,
            'tenant_id'         => $user->tenant_id,
            'timelineable_type' => EmailMessage::class,
            'timelineable_id'   => $draft->id,
            'event_type'        => 'draft_created',
            'description'       => "Email draft created via AI integration ({$this->apiClient($request)->source_type}). Subject: {$draft->subject}",
        ]);

        $this->audit($request, 'create_draft', 'email_message', $draft->id, 'medium',
            "contact_id={$contact->id}, opportunity_id=" . ($opportunity?->id ?? 'null'),
            "draft_id={$draft->id}",
        );

        return response()->json(['data' => $this->format($draft), 'message' => 'Draft saved. Review it in the CRM before sending.'], 201);
    }

    private function format(EmailMessage $d): array
    {
        return [
            'id'             => $d->id,
            'subject'        => $d->subject,
            'to_email'       => $d->to_email,
            'to_name'        => $d->to_name,
            'status'         => $d->status,
            'contact_id'     => $d->contact_id,
            'opportunity_id' => $d->opportunity_id,
            'is_follow_up'   => $d->is_follow_up,
            'created_at'     => $d->created_at?->toISOString(),
            'preview'        => substr(strip_tags($d->body ?? ''), 0, 200),
        ];
    }
}
