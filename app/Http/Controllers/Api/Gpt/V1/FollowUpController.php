<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use App\Models\Opportunity;
use App\Models\SuppressionList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowUpController extends GptController
{
    public function due(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $followUps = FollowUp::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('due_at', '<=', now()->endOfDay())
            ->with(['contact', 'opportunity'])
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data'  => $followUps->map(fn ($f) => $this->format($f)),
            'count' => $followUps->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_id'        => 'required|integer',
            'opportunity_id'    => 'nullable|integer',
            'due_at'            => 'required|date|after:now',
            'mode'              => 'nullable|in:reminder_only,draft',
            'notes'             => 'nullable|string|max:2000',
            'suggested_subject' => 'nullable|string|max:500',
            'suggested_body'    => 'nullable|string|max:20000',
        ]);

        $user    = $this->apiUser($request);
        $contact = Contact::where('user_id', $user->id)->findOrFail($data['contact_id']);

        // Block suppressed contacts
        $suppressed = in_array($contact->status, ['suppressed', 'bounced'], true);
        if (! $suppressed && $contact->email) {
            $suppressed = SuppressionList::isSuppressed($user->id, $contact->email);
        }

        if ($suppressed) {
            $this->audit($request, 'create_followup_blocked', 'contact', $contact->id, 'medium',
                "contact_id={$contact->id}", 'blocked: suppressed', 'blocked');
            return response()->json(['error' => 'Cannot schedule follow-up for a suppressed contact.'], 422);
        }

        // Warn if contact has recently replied
        $recentReply = InboxMessage::where('user_id', $user->id)
            ->where('matched_contact_id', $contact->id)
            ->whereNotNull('matched_outbound_id')
            ->where('received_at', '>=', now()->subDays(7))
            ->exists();

        // Verify opportunity
        $opportunity = null;
        if (! empty($data['opportunity_id'])) {
            $opportunity = Opportunity::where('user_id', $user->id)->findOrFail($data['opportunity_id']);
        }

        $followUp = FollowUp::create([
            'user_id'        => $user->id,
            'tenant_id'      => $user->tenant_id,
            'contact_id'     => $contact->id,
            'opportunity_id' => $opportunity?->id,
            'due_at'         => $data['due_at'],
            'status'         => 'pending',
            'subject'        => $data['suggested_subject'] ?? null,
            'body'           => $data['suggested_body'] ?? null,
            'follow_up_number' => 1,
        ]);

        $this->audit($request, 'create_followup', 'follow_up', $followUp->id, 'low',
            "contact_id={$contact->id}, due_at={$data['due_at']}",
            "id={$followUp->id}",
        );

        return response()->json([
            'data'         => $this->format($followUp),
            'recent_reply' => $recentReply,
            'warning'      => $recentReply ? 'This contact replied within the last 7 days. Confirm you still want to follow up.' : null,
            'message'      => 'Follow-up scheduled as reminder-only. Auto-sending is disabled.',
        ], 201);
    }

    private function format(FollowUp $f): array
    {
        return [
            'id'             => $f->id,
            'contact_id'     => $f->contact_id,
            'opportunity_id' => $f->opportunity_id,
            'due_at'         => $f->due_at?->toISOString(),
            'status'         => $f->status,
            'subject'        => $f->subject,
            'contact'        => $f->contact?->only(['id', 'first_name', 'last_name', 'email']),
            'opportunity'    => $f->opportunity?->only(['id', 'title', 'status']),
        ];
    }
}
