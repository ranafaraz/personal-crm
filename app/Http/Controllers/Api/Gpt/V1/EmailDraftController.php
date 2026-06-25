<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Jobs\SendEmailJob;
use App\Models\ApiAttachment;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailSignature;
use App\Models\Opportunity;
use App\Models\SuppressionList;
use App\Models\TimelineEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailDraftController extends GptController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'opportunity_id' => 'nullable|integer',
            'contact_id'     => 'nullable|integer',
            'status'         => 'nullable|string|max:30',
            'per_page'       => 'nullable|integer|min:1|max:100',
            'page'           => 'nullable|integer|min:1',
        ]);

        $user = $this->apiUser($request);

        $query = EmailMessage::where('user_id', $user->id)
            ->where('direction', 'outbound')
            // Default to draft-only unless an explicit status filter is given.
            ->where('status', $filters['status'] ?? 'draft')
            ->with(['contact', 'opportunity', 'emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion']);

        if (! empty($filters['opportunity_id'])) {
            $query->where('opportunity_id', $filters['opportunity_id']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        $perPage = (int) ($filters['per_page'] ?? 50);
        $page    = (int) ($filters['page'] ?? 1);
        $total   = (clone $query)->count();

        $drafts = $query->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        return $this->listResponse(
            $drafts->map(fn ($d) => $this->format($d))->values(),
            $perPage,
            $total,
            [],
            $page
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_id'      => 'required|integer',
            'opportunity_id'  => 'nullable|integer',
            'subject'         => 'required|string|max:500',
            'body'            => 'required|string|max:50000',
            'draft_type'      => ['nullable', Rule::in(['initial_outreach', 'follow_up', 'thank_you', 'general'])],
            'tone'            => ['nullable', Rule::in(['professional', 'casual', 'formal'])],
            'requires_review' => 'nullable|boolean',
            'signature_id'    => 'nullable|integer',
            'attachment_ids'  => 'nullable|array|max:10',
            'attachment_ids.*'=> 'integer',
        ]);

        $user    = $this->apiUser($request);
        $isMcp   = $this->apiClient($request)->source_type === 'mcp';

        // Bug 2: strip AI-generated "Subject:/From:/To:" header lines from body
        $data['body'] = $this->sanitizeDraftBody($data['body'], $data['subject']);

        // Verify contact ownership
        $contact = Contact::where('user_id', $user->id)->findOrFail($data['contact_id']);

        // Block suppressed contacts
        $suppressed = in_array($contact->status, ['suppressed', 'bounced'], true);
        if (! $suppressed && $contact->email) {
            $suppressed = SuppressionList::isSuppressed($user->id, $contact->email);
        }

        if ($suppressed) {
            $this->audit($request, 'create_draft_blocked', 'contact', $contact->id, 'medium',
                "contact_id={$contact->id}", 'blocked: suppressed contact', 'blocked');
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

        // Resolve signature: explicit id → user default → none (Bug 3)
        $signature         = null;
        $renderedSignature = null;
        if (! empty($data['signature_id'])) {
            $signature = EmailSignature::where('user_id', $user->id)->findOrFail($data['signature_id']);
        } else {
            $signature = EmailSignature::where('user_id', $user->id)->where('is_default', true)->first();
        }
        if ($signature) {
            $renderedSignature = $signature->renderHtml();
        }

        // Guard against duplicate creation: reject if same subject+recipient+opportunity
        // was created by this user in the last 60 seconds (covers MCP agent loops).
        $recentDuplicate = EmailMessage::where('user_id', $user->id)
            ->where('to_email', $contact->email)
            ->where('subject', $data['subject'])
            ->where('direction', 'outbound')
            ->when(! empty($data['opportunity_id']), fn ($q) => $q->where('opportunity_id', $data['opportunity_id']))
            ->where('created_at', '>=', now()->subSeconds(60))
            ->first();

        if ($recentDuplicate) {
            $this->audit($request, 'create_draft_duplicate', 'email_message', $recentDuplicate->id, 'medium',
                "contact_id={$contact->id}", 'blocked: duplicate within 60s', 'blocked');
            return response()->json([
                'error'            => 'An identical draft for this recipient was created less than 60 seconds ago. Use the existing draft or wait before creating another.',
                'existing_draft_id'=> $recentDuplicate->id,
            ], 409);
        }

        // Resolve sender account
        $emailAccount = EmailAccount::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        $draft = EmailMessage::create([
            'user_id'            => $user->id,
            'tenant_id'          => $user->tenant_id,
            'email_account_id'   => $emailAccount?->id,
            'contact_id'         => $contact->id,
            'opportunity_id'     => $opportunity?->id,
            'email_signature_id' => $signature?->id,
            'rendered_signature' => $renderedSignature,
            'subject'            => $data['subject'],
            'body'               => $data['body'],
            'to_email'           => $contact->email,
            'to_name'            => $contact->full_name,
            'status'             => 'draft',
            'direction'          => 'outbound',
            'is_follow_up'       => ($data['draft_type'] ?? '') === 'follow_up',
        ]);

        // Attach any provided attachments
        $attachmentWarnings = [];
        if (! empty($data['attachment_ids'])) {
            $attachments = ApiAttachment::where('user_id', $user->id)
                ->whereIn('id', $data['attachment_ids'])
                ->get();

            $missingIds = array_values(array_diff($data['attachment_ids'], $attachments->pluck('id')->toArray()));
            if (! empty($missingIds)) {
                $draft->forceDelete();
                return response()->json([
                    'error'       => 'One or more attachment_ids were not found. Use POST /attachments to register files and get valid IDs. Document IDs from POST /documents are not interchangeable with attachment IDs.',
                    'missing_ids' => $missingIds,
                ], 422);
            }

            $syncData = [];
            foreach ($attachments as $att) {
                $syncData[$att->id] = ['added_by_user_id' => $user->id];
                if ($att->validation_status === 'warning') {
                    $attachmentWarnings = array_merge($attachmentWarnings, $att->validation_warnings ?? []);
                }
            }
            $draft->apiAttachments()->sync($syncData);
        }

        // Keep opportunity–contact pivot in sync automatically.
        if ($opportunity) {
            $opportunity->contacts()->syncWithoutDetaching([$contact->id]);
        }

        TimelineEvent::create([
            'user_id'           => $user->id,
            'tenant_id'         => $user->tenant_id,
            'timelineable_type' => EmailMessage::class,
            'timelineable_id'   => $draft->id,
            'event_type'        => 'draft_created',
            'description'       => "Email draft created via AI integration ({$this->apiClient($request)->source_type}). Subject: {$draft->subject}",
            'happened_at'       => now(),
        ]);

        $this->audit($request, 'create_draft', 'email_message', $draft->id, 'medium',
            "contact_id={$contact->id}, opportunity_id=" . ($opportunity?->id ?? 'null') .
            ", signature_id=" . ($signature?->id ?? 'null') .
            ", attachment_count=" . count($data['attachment_ids'] ?? []),
            "draft_id={$draft->id}");

        $draft->load(['emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion']);

        $notice = $isMcp
            ? 'MCP source — confirmation gate bypassed. Draft is ready to send.'
            : 'Draft saved. Review it in the CRM before sending.';

        $response = [
            'data'                 => $this->format($draft, !$isMcp),
            'confirmation_required'=> ! $isMcp,
            'send_status'          => 'draft',
            'message'              => $notice,
        ];

        if (! empty($attachmentWarnings)) {
            $response['attachment_validation_warnings'] = array_values(array_unique($attachmentWarnings));
            $response['warning'] = 'Some attachments contain sensitive documents. Confirm the recipient has requested these before sending.';
        }

        return response()->json($response, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'subject'         => 'sometimes|string|max:500',
            'body'            => 'sometimes|string|max:50000',
            'signature_id'    => 'sometimes|nullable|integer',
            'attachment_ids'  => 'sometimes|array|max:10',
            'attachment_ids.*'=> 'integer',
        ]);

        $user  = $this->apiUser($request);
        $isMcp = $this->apiClient($request)->source_type === 'mcp';

        $draft = EmailMessage::where('user_id', $user->id)
            ->where('status', 'draft')
            ->where('direction', 'outbound')
            ->findOrFail($id);

        if (array_key_exists('subject', $data)) {
            $draft->subject = $data['subject'];
        }
        if (array_key_exists('body', $data)) {
            // Bug 2: sanitize body on update too
            $subject = $draft->subject;
            $draft->body    = $this->sanitizeDraftBody($data['body'], $subject);
            $draft->subject = $subject;
        }

        // Re-resolve signature and re-snapshot its rendered HTML (null clears it).
        if (array_key_exists('signature_id', $data)) {
            if (empty($data['signature_id'])) {
                $draft->email_signature_id = null;
                $draft->rendered_signature = null;
            } else {
                $signature = EmailSignature::where('user_id', $user->id)->findOrFail($data['signature_id']);
                $draft->email_signature_id = $signature->id;
                $draft->rendered_signature = $signature->renderHtml();
            }
        }

        $draft->save();

        // Replace the attachment set when provided (verifying ownership).
        if (array_key_exists('attachment_ids', $data)) {
            $attachments = ApiAttachment::where('user_id', $user->id)
                ->whereIn('id', $data['attachment_ids'])
                ->get();

            $missingIds = array_values(array_diff($data['attachment_ids'], $attachments->pluck('id')->toArray()));
            if (! empty($missingIds)) {
                return response()->json([
                    'error'       => 'One or more attachment_ids were not found. Register files via POST /attachments first.',
                    'missing_ids' => $missingIds,
                ], 422);
            }

            $syncData = [];
            foreach ($attachments as $att) {
                $syncData[$att->id] = ['added_by_user_id' => $user->id];
            }
            $draft->apiAttachments()->sync($syncData);
        }

        $this->audit($request, 'update_draft', 'email_message', $draft->id, 'low',
            'fields=' . implode(',', array_keys($data)), "draft_id={$draft->id}");

        $draft->load(['emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion']);

        $notice = $isMcp
            ? 'Draft updated. MCP source — use sendDraft to send immediately.'
            : 'Draft updated. Review it in the CRM before sending.';

        return response()->json([
            'data'                  => $this->format($draft, !$isMcp),
            'confirmation_required' => ! $isMcp,
            'send_status'           => 'draft',
            'message'               => $notice,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user  = $this->apiUser($request);
        $draft = EmailMessage::where('user_id', $user->id)
            ->where('status', 'draft')
            ->where('direction', 'outbound')
            ->findOrFail($id);

        $draft->delete();

        $this->audit($request, 'delete_draft', 'email_message', $id, 'low', "draft_id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Send a draft. For MCP clients the job is dispatched synchronously so the
     * response reflects the real send outcome. Non-MCP clients use the async
     * scheduled-send pipeline (crm:send-scheduled → SendEmailJob). Scope: email:send.
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $user  = $this->apiUser($request);
        $isMcp = $this->apiClient($request)->source_type === 'mcp';

        $draft = EmailMessage::where('user_id', $user->id)
            ->where('direction', 'outbound')
            ->findOrFail($id);

        if ($draft->status !== 'draft') {
            return response()->json([
                'error'          => 'Only a pending draft can be sent.',
                'current_status' => $draft->status,
            ], 422);
        }

        if (empty($draft->to_email)) {
            return response()->json(['error' => 'Draft has no recipient email address.'], 422);
        }

        // Block suppressed recipients at send time as a final guard.
        if (SuppressionList::isSuppressed($user->id, $draft->to_email)) {
            $this->audit($request, 'send_draft_blocked', 'email_message', $draft->id, 'high',
                "to={$draft->to_email}", 'blocked: suppressed', 'blocked');
            return response()->json(['error' => 'Recipient is on the suppression list.'], 422);
        }

        if ($isMcp) {
            // MCP: dispatch synchronously so we return the real outcome
            try {
                SendEmailJob::dispatchSync($draft);
                $draft->refresh();
            } catch (\Throwable $e) {
                $this->audit($request, 'send_draft', 'email_message', $draft->id, 'high',
                    "to={$draft->to_email}", 'exception: ' . $e->getMessage(), 'failed');
                return response()->json([
                    'error'    => 'Email send failed: ' . $e->getMessage(),
                    'draft_id' => $draft->id,
                ], 500);
            }

            // Check the actual outcome — dispatchSync won't throw if sendEmail() returned false.
            if ($draft->status !== 'sent') {
                $reason = $draft->failure_reason ?? 'SMTP error or send timed out. Check your email account settings.';
                $this->audit($request, 'send_draft', 'email_message', $draft->id, 'high',
                    "to={$draft->to_email}", "send failed: {$reason}", 'failed');
                return response()->json([
                    'error'          => 'Email failed to send: ' . $reason,
                    'draft_id'       => $draft->id,
                    'current_status' => $draft->status,
                ], 500);
            }

            $this->audit($request, 'send_draft', 'email_message', $draft->id, 'high',
                "to={$draft->to_email}", "draft_id={$draft->id} sent via mcp", 'success');

            return response()->json([
                'message'               => 'Email sent successfully.',
                'draft_id'              => $draft->id,
                'sent_to'               => $draft->to_email,
                'sent_at'               => $draft->sent_at?->toISOString(),
                'bypassed_confirmation' => true,
            ]);
        }

        // Non-MCP: queue via existing scheduled-send pipeline
        $draft->status       = 'scheduled';
        $draft->scheduled_at = now();
        $draft->save();

        TimelineEvent::create([
            'user_id'           => $user->id,
            'tenant_id'         => $user->tenant_id,
            'timelineable_type' => EmailMessage::class,
            'timelineable_id'   => $draft->id,
            'event_type'        => 'send_requested',
            'description'       => "Send requested via AI integration ({$this->apiClient($request)->source_type}). Subject: {$draft->subject}",
            'happened_at'       => now(),
        ]);

        $this->audit($request, 'send_draft', 'email_message', $draft->id, 'high',
            "to={$draft->to_email}", "draft_id={$draft->id} queued");

        return response()->json([
            'queued'   => true,
            'draft_id' => $draft->id,
            'notice'   => 'Email queued for send. You will be notified on delivery.',
        ]);
    }

    /**
     * Schedule a draft for future delivery. Sets status='scheduled' and
     * scheduled_at to the requested UTC time. crm:send-scheduled picks it up
     * when scheduled_at <= now(). Scope: email:send.
     */
    public function schedule(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'send_at' => 'required|date|after:now',
        ]);

        $user  = $this->apiUser($request);
        $draft = EmailMessage::where('user_id', $user->id)
            ->where('direction', 'outbound')
            ->where('status', 'draft')
            ->findOrFail($id);

        if (empty($draft->to_email)) {
            return response()->json(['error' => 'Draft has no recipient email address.'], 422);
        }

        if (SuppressionList::isSuppressed($user->id, $draft->to_email)) {
            return response()->json(['error' => 'Recipient is on the suppression list.'], 422);
        }

        $sendAt = Carbon::parse($data['send_at'])->utc();

        $draft->update([
            'status'       => 'scheduled',
            'scheduled_at' => $sendAt,
        ]);

        $this->audit($request, 'schedule_draft', 'email_message', $draft->id, 'medium',
            "send_at={$sendAt->toISOString()}", "draft_id={$draft->id} scheduled");

        return response()->json([
            'message'      => 'Email scheduled. It will be dispatched automatically at the scheduled time.',
            'draft_id'     => $draft->id,
            'scheduled_at' => $draft->scheduled_at?->toISOString(),
            'status'       => 'scheduled',
        ]);
    }

    /**
     * Unschedule a previously scheduled draft, reverting it to draft status.
     * Scope: email:send.
     */
    public function unschedule(Request $request, int $id): JsonResponse
    {
        $user  = $this->apiUser($request);
        $draft = EmailMessage::where('user_id', $user->id)
            ->where('direction', 'outbound')
            ->where('status', 'scheduled')
            ->findOrFail($id);

        $draft->update([
            'status'       => 'draft',
            'scheduled_at' => null,
        ]);

        $this->audit($request, 'unschedule_draft', 'email_message', $draft->id, 'low',
            "draft_id={$id}", "draft_id={$draft->id} unscheduled");

        return response()->json([
            'message'  => 'Email unscheduled. Draft is ready for review or rescheduling.',
            'draft_id' => $draft->id,
            'status'   => 'draft',
        ]);
    }

    public function format(EmailMessage $d, bool $confirmationRequired = true): array
    {
        $attachments    = $d->relationLoaded('apiAttachments') ? $d->apiAttachments : collect();
        $attachmentIds  = $attachments->pluck('id')->toArray();
        $hasWarnings    = $attachments->where('validation_status', 'warning')->isNotEmpty();
        $linkedDocuments = $d->relationLoaded('apiDocumentLinks')
            ? $this->formatLinkedDocuments($d->apiDocumentLinks)
            : [];

        return [
            'id'                           => $d->id,
            'subject'                      => $d->subject,
            'body'                         => $d->body,
            'rendered_body'                => $d->rendered_body,
            'to_email'                     => $d->to_email,
            'to_name'                      => $d->to_name,
            'status'                       => $d->status,
            'send_status'                  => $d->status,
            'contact_id'                   => $d->contact_id,
            'opportunity_id'               => $d->opportunity_id,
            'signature_id'                 => $d->email_signature_id,
            'signature_name'               => $d->emailSignature?->name,
            'rendered_signature'           => $d->rendered_signature,
            'attachment_ids'               => $attachmentIds,
            'attachment_count'             => count($attachmentIds),
            'attachment_validation_status' => $hasWarnings ? 'warning' : 'valid',
            'linked_documents'             => $linkedDocuments,
            'linked_document_count'        => count($linkedDocuments),
            'linked_documents_notice'      => 'linked_documents are reference files attached via uploadDocument — they are NOT sent with this email. Only items in attachment_ids (added via uploadAttachment + attachment_ids) are sent.',
            'confirmation_required'        => $confirmationRequired,
            'is_follow_up'                 => $d->is_follow_up,
            'created_at'                   => $d->created_at?->toISOString(),
            'preview'                      => substr(strip_tags($d->body ?? ''), 0, 200),
        ];
    }

    /**
     * Strip AI-generated email header lines (Subject:, From:, To:, etc.) that
     * sometimes leak into the body field when the model generates a full email
     * literal instead of just the body text.
     */
    private function sanitizeDraftBody(string $body, string &$subject): string
    {
        $body = ltrim($body);

        // Extract subject if the body starts with a "Subject:" line
        if (preg_match('/^Subject:\s*(.+?)\r?\n/i', $body, $matches)) {
            if (empty($subject)) {
                $subject = trim($matches[1]);
            }
            $body = preg_replace('/^Subject:\s*.+?\r?\n+/i', '', $body);
        }

        // Strip remaining header lines (From:, To:, Date:, CC:, BCC:)
        $body = preg_replace('/^(From|To|Date|CC|BCC):\s*.+?\r?\n/im', '', $body ?? '');

        return ltrim($body);
    }
}
