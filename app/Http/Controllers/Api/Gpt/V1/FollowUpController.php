<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\ApiAttachment;
use App\Models\Contact;
use App\Models\EmailSignature;
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
            ->with(['contact', 'opportunity', 'emailSignature', 'apiAttachments'])
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
            'contact_id'              => 'required|integer',
            'opportunity_id'          => 'nullable|integer',
            'due_at'                  => 'required|date|after:now',
            'mode'                    => 'nullable|in:reminder_only,draft',
            'notes'                   => 'nullable|string|max:2000',
            'suggested_subject'       => 'nullable|string|max:500',
            'suggested_body'          => 'nullable|string|max:20000',
            'signature_id'            => 'nullable|integer',
            'suggested_attachment_ids'   => 'nullable|array|max:10',
            'suggested_attachment_ids.*' => 'integer',
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

        // Resolve signature and snapshot rendered HTML
        $signature         = null;
        $renderedSignature = null;
        if (! empty($data['signature_id'])) {
            $signature         = EmailSignature::where('user_id', $user->id)->findOrFail($data['signature_id']);
            $renderedSignature = $signature->renderHtml();
        }

        $followUp = FollowUp::create([
            'user_id'            => $user->id,
            'tenant_id'          => $user->tenant_id,
            'contact_id'         => $contact->id,
            'opportunity_id'     => $opportunity?->id,
            'due_at'             => $data['due_at'],
            'status'             => 'pending',
            'subject'            => $data['suggested_subject'] ?? null,
            'body'               => $data['suggested_body'] ?? null,
            'email_signature_id' => $signature?->id,
            'rendered_signature' => $renderedSignature,
            'follow_up_number'   => 1,
        ]);

        // Attach suggested attachments
        $attachmentWarnings = [];
        if (! empty($data['suggested_attachment_ids'])) {
            $attachments = ApiAttachment::where('user_id', $user->id)
                ->whereIn('id', $data['suggested_attachment_ids'])
                ->get();

            foreach ($attachments as $att) {
                if ($att->validation_status === 'warning') {
                    $attachmentWarnings = array_merge($attachmentWarnings, $att->validation_warnings ?? []);
                }
            }
            $followUp->apiAttachments()->sync($attachments->pluck('id')->toArray());
        }

        // Keep opportunity–contact pivot in sync automatically.
        if ($opportunity) {
            $opportunity->contacts()->syncWithoutDetaching([$contact->id]);
        }

        $this->audit($request, 'create_followup', 'follow_up', $followUp->id, 'low',
            "contact_id={$contact->id}, due_at={$data['due_at']}" .
            ", signature_id=" . ($signature?->id ?? 'null') .
            ", attachment_count=" . count($data['suggested_attachment_ids'] ?? []),
            "id={$followUp->id}");

        $followUp->load(['emailSignature', 'apiAttachments']);

        $response = [
            'data'                  => $this->format($followUp),
            'recent_reply'          => $recentReply,
            'confirmation_required' => true,
            'send_status'           => 'pending',
            'warning'               => $recentReply ? 'This contact replied within the last 7 days. Confirm you still want to follow up.' : null,
            'message'               => 'Follow-up scheduled as reminder-only. Auto-sending is disabled.',
        ];

        if (! empty($attachmentWarnings)) {
            $response['attachment_validation_warnings'] = array_values(array_unique($attachmentWarnings));
            $response['warning'] = trim(($response['warning'] ?? '') . ' Some suggested attachments contain sensitive documents.');
        }

        return response()->json($response, 201);
    }

    public function format(FollowUp $f): array
    {
        $attachments   = $f->relationLoaded('apiAttachments') ? $f->apiAttachments : collect();
        $attachmentIds = $attachments->pluck('id')->toArray();

        return [
            'id'                           => $f->id,
            'contact_id'                   => $f->contact_id,
            'opportunity_id'               => $f->opportunity_id,
            'due_at'                       => $f->due_at?->toISOString(),
            'status'                       => $f->status,
            'send_status'                  => $f->status,
            'subject'                      => $f->subject,
            'signature_id'                 => $f->email_signature_id,
            'signature_name'               => $f->emailSignature?->name,
            'rendered_signature'           => $f->rendered_signature,
            'suggested_attachment_ids'     => $attachmentIds,
            'attachment_count'             => count($attachmentIds),
            'attachment_validation_status' => $attachments->where('validation_status', 'warning')->isNotEmpty() ? 'warning' : 'valid',
            'confirmation_required'        => true,
            'contact'                      => $f->contact?->only(['id', 'first_name', 'last_name', 'email']),
            'opportunity'                  => $f->opportunity?->only(['id', 'title', 'status']),
        ];
    }
}
