<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\InboxMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReplyController extends GptController
{
    public function recent(Request $request): JsonResponse
    {
        $request->validate([
            'since'          => 'nullable|date',
            'opportunity_id' => 'nullable|integer',
            'contact_id'     => 'nullable|integer',
            'sentiment'      => ['nullable', Rule::in(['positive', 'neutral', 'negative'])],
            'only_human'     => 'nullable|boolean',
            'limit'          => 'nullable|integer|min:1|max:50',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 20), 50);

        $query = InboxMessage::where('user_id', $user->id)
            ->whereNotNull('matched_outbound_id')
            ->with(['matchedContact', 'matchedOpportunity']);

        if ($since = $request->input('since')) {
            $query->where('received_at', '>=', $since);
        }
        if ($opportunityId = $request->input('opportunity_id')) {
            $query->where('matched_opportunity_id', $opportunityId);
        }
        if ($contactId = $request->input('contact_id')) {
            $query->where('matched_contact_id', $contactId);
        }
        if ($sentiment = $request->input('sentiment')) {
            $query->where('sentiment', $sentiment);
        }
        if (filter_var($request->input('only_human'), FILTER_VALIDATE_BOOLEAN)) {
            // Exclude test/auto-ack noise: CRM-E2E subjects, auto-responder senders,
            // internal domains, and no-reply addresses.
            $query->where(function ($q) {
                $q->where('subject', 'not like', '%CRM-E2E%')
                  ->where('subject', 'not like', '%[TEST]%')
                  ->where('subject', 'not like', '%Auto:%')
                  ->where('subject', 'not like', '%Automatic reply%')
                  ->where('subject', 'not like', '%Out of office%')
                  ->where('subject', 'not like', '%Autosvar%');
            })
            ->where('from_email', 'not like', 'noreply@%')
            ->where('from_email', 'not like', 'no-reply@%')
            ->where('from_email', 'not like', 'mailer-daemon@%')
            ->where('from_email', 'not like', '%@dexdevs.com')
            ->where('from_email', 'not like', '%@example.com')
            ->where('from_email', 'not like', '%info@%');
        }

        $replies = $query->orderByDesc('received_at')->limit($limit)->get();

        return $this->listResponse($replies->map(fn ($r) => [
            'id'          => $r->id,
            'from_email'  => $r->from_email,
            'from_name'   => $r->from_name,
            'subject'     => $r->subject,
            'sentiment'   => $r->sentiment,
            'received_at' => $r->received_at?->toISOString(),
            'contact'     => $r->matchedContact?->only(['id', 'first_name', 'last_name', 'email']),
            'opportunity' => $r->matchedOpportunity?->only(['id', 'title', 'status']),
            'preview'     => substr(strip_tags($r->body_text ?? ''), 0, 300),
        ])->values(), $limit);
    }
}
