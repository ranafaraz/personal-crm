<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\ApiAttachment;
use App\Models\Contact;
use App\Models\EmailSignature;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use App\Models\Opportunity;
use App\Models\SuppressionList;
use App\Services\FollowUpService;
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
            ->with(['contact', 'opportunity', 'emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion'])
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        return $this->listResponse($followUps->map(fn ($f) => $this->format($f))->values(), 50);
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

        $followUp->load(['emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion']);

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

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'         => 'nullable|string|max:50',
            'contact_id'     => 'nullable|integer',
            'opportunity_id' => 'nullable|integer',
            'limit'          => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = FollowUp::where('user_id', $user->id)
            ->with(['contact', 'opportunity', 'emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($contactId = $request->input('contact_id')) {
            $query->where('contact_id', $contactId);
        }
        if ($opportunityId = $request->input('opportunity_id')) {
            $query->where('opportunity_id', $opportunityId);
        }

        $followUps = $query->orderBy('due_at')->limit($limit)->get();

        return $this->listResponse($followUps->map(fn ($f) => $this->format($f))->values(), $limit);
    }

    /**
     * Manually send a pending follow-up immediately, regardless of due_at.
     * Scope: email:send + followups:update.
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $user     = $this->apiUser($request);
        $followUp = FollowUp::where('user_id', $user->id)
            ->with(['opportunity', 'contact', 'emailAccount', 'emailMessage', 'emailSignature', 'apiAttachments'])
            ->findOrFail($id);

        if ($followUp->status !== 'pending') {
            return response()->json([
                'error'          => 'Only a pending follow-up can be sent.',
                'current_status' => $followUp->status,
            ], 422);
        }

        $result = app(FollowUpService::class)->sendFollowUpNow($followUp);

        if (! $result['success']) {
            $this->audit($request, 'send_followup', 'follow_up', $followUp->id, 'high',
                "id={$id}", 'failed: ' . $result['error'], 'failed');
            return response()->json(['error' => $result['error']], 500);
        }

        $this->audit($request, 'send_followup', 'follow_up', $followUp->id, 'high',
            "id={$id}", 'sent manually', 'success');

        $followUp->refresh();
        $followUp->load(['contact', 'opportunity', 'emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion']);

        return response()->json([
            'message' => 'Follow-up sent successfully.',
            'data'    => $this->format($followUp),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'due_at'            => 'sometimes|date',
            'auto_send'         => 'sometimes|boolean',
            'notes'             => 'sometimes|nullable|string|max:2000',
            'suggested_subject' => 'sometimes|nullable|string|max:500',
            'suggested_body'    => 'sometimes|nullable|string|max:20000',
            'status'            => 'sometimes|in:pending,sent,cancelled,skipped',
        ]);

        $user     = $this->apiUser($request);
        $followUp = FollowUp::where('user_id', $user->id)->findOrFail($id);

        if (array_key_exists('due_at', $data)) {
            $followUp->due_at = $data['due_at'];
        }
        if (array_key_exists('auto_send', $data)) {
            $followUp->auto_send = $data['auto_send'];
        }
        if (array_key_exists('suggested_subject', $data)) {
            $followUp->subject = $data['suggested_subject'];
        }
        if (array_key_exists('suggested_body', $data)) {
            $followUp->body = $data['suggested_body'];
        }
        if (array_key_exists('status', $data)) {
            $followUp->status = $data['status'];
        }

        $followUp->save();

        $this->audit($request, 'update_followup', 'follow_up', $followUp->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$followUp->id}");

        $followUp->load(['contact', 'opportunity', 'emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion']);

        return response()->json(['data' => $this->format($followUp)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $followUp = FollowUp::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $followUp->delete();

        $this->audit($request, 'delete_followup', 'follow_up', $id, 'low', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        $user     = $this->apiUser($request);
        $followUp = FollowUp::where('user_id', $user->id)->findOrFail($id);

        if ($followUp->status !== 'pending') {
            return response()->json([
                'error'          => 'Only a pending follow-up can be cancelled.',
                'current_status' => $followUp->status,
            ], 422);
        }

        $followUp->update([
            'status'        => 'cancelled',
            'cancel_reason' => $data['cancel_reason'] ?? 'manual',
        ]);

        $this->audit($request, 'cancel_followup', 'follow_up', $followUp->id, 'low',
            "id={$id}, reason=" . ($data['cancel_reason'] ?? 'manual'));

        $followUp->load(['contact', 'opportunity', 'emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion']);

        return response()->json(['data' => $this->format($followUp)]);
    }

    public function format(FollowUp $f): array
    {
        $attachments   = $f->relationLoaded('apiAttachments') ? $f->apiAttachments : collect();
        $attachmentIds = $attachments->pluck('id')->toArray();
        $linkedDocuments = $f->relationLoaded('apiDocumentLinks')
            ? $this->formatLinkedDocuments($f->apiDocumentLinks)
            : [];

        return [
            'id'                           => $f->id,
            'contact_id'                   => $f->contact_id,
            'opportunity_id'               => $f->opportunity_id,
            'due_at'                       => $f->due_at?->toISOString(),
            'status'                       => $f->status,
            'send_status'                  => $f->status,
            'auto_send'                    => (bool) $f->auto_send,
            'subject'                      => $f->subject,
            'sent_at'                      => $f->sent_at?->toISOString(),
            'cancel_reason'                => $f->cancel_reason,
            'signature_id'                 => $f->email_signature_id,
            'signature_name'               => $f->emailSignature?->name,
            'rendered_signature'           => $f->rendered_signature,
            'suggested_attachment_ids'     => $attachmentIds,
            'attachment_count'             => count($attachmentIds),
            'attachment_validation_status' => $attachments->where('validation_status', 'warning')->isNotEmpty() ? 'warning' : 'valid',
            'linked_documents'             => $linkedDocuments,
            'linked_document_count'        => count($linkedDocuments),
            'linked_documents_notice'      => 'linked_documents are reference files attached via uploadDocument — they are NOT sent with this follow-up. Only items in suggested_attachment_ids (added via uploadAttachment + suggested_attachment_ids) are sendable.',
            'confirmation_required'        => true,
            'contact'                      => $f->contact?->only(['id', 'first_name', 'last_name', 'email']),
            'opportunity'                  => $f->opportunity?->only(['id', 'title', 'status']),
        ];
    }
}
