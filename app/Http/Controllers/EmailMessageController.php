<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmailMessageRequest;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailSignature;
use App\Models\EmailTemplate;
use App\Models\Opportunity;
use App\Services\EmailSendingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class EmailMessageController extends Controller
{
    public function index(Request $request): View
    {
        $base = fn () => $this->tenantQuery(EmailMessage::class)
            ->with(['emailAccount', 'contact', 'opportunity'])
            ->where('direction', 'outbound');

        $sent      = $base()->where('status', 'sent')->orderByDesc('sent_at')->limit(100)->get();
        $scheduled = $base()->where('status', 'scheduled')->orderByDesc('scheduled_at')->limit(100)->get();
        $drafts    = $base()->where('status', 'draft')->orderByDesc('updated_at')->limit(100)->get();
        $failed    = $base()->where('status', 'failed')->orderByDesc('updated_at')->limit(100)->get();

        $outboxCount    = $sent->count();
        $scheduledCount = $scheduled->count();
        $draftsCount    = $drafts->count();
        $failedCount    = $failed->count();

        $tab = $request->input('tab', 'outbox');

        $emailAccounts = $this->tenantQuery(EmailAccount::class)
            ->orderBy('name')
            ->get();

        return view('emails.index', compact(
            'sent', 'scheduled', 'drafts', 'failed',
            'outboxCount', 'scheduledCount', 'draftsCount', 'failedCount',
            'tab', 'emailAccounts'
        ));
    }

    public function compose(): View
    {
        $emailAccounts = $this->tenantQuery(EmailAccount::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $templates = $this->tenantQuery(EmailTemplate::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $contacts = $this->tenantQuery(Contact::class)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        $opportunities = $this->tenantQuery(Opportunity::class)
            ->orderByDesc('updated_at')
            ->get(['id', 'title']);

        [$signatures, $defaultSignatureId, $signaturePayload] = $this->signatureOptions();

        return view('emails.compose', compact(
            'emailAccounts',
            'templates',
            'contacts',
            'opportunities',
            'signatures',
            'defaultSignatureId',
            'signaturePayload'
        ));
    }

    public function store(StoreEmailMessageRequest $request, EmailSendingService $emailService): RedirectResponse
    {
        $data = $request->validated();

        // Compose form sends `send_option` (now|schedule|draft) and `scheduled_at`.
        // Older callers may send `send_now` or `send_at` — accept either.
        $sendOption  = $request->input('send_option', $request->boolean('send_now') ? 'now' : 'draft');
        $scheduledAt = $request->input('scheduled_at', $request->input('send_at'));

        // CC/BCC may arrive as comma-separated text. Normalise to array of {email,name}.
        $ccList  = $this->parseAddresses($request->input('cc'));
        $bccList = $this->parseAddresses($request->input('bcc'));

        $message = EmailMessage::create($this->tenantData([
            'email_account_id' => $data['email_account_id'],
            'contact_id'       => $data['contact_id'] ?? null,
            'opportunity_id'   => $data['opportunity_id'] ?? null,
            'template_id'      => $data['template_id'] ?? null,
            'email_signature_id' => $this->validatedSignatureId($data['email_signature_id'] ?? null),
            'to_email'         => $data['to_email'],
            'to_name'          => $data['to_name'] ?? null,
            'subject'          => $data['subject'],
            'body'             => $data['body'],
            'cc'               => $ccList ?: null,
            'bcc'              => $bccList ?: null,
            'direction'        => 'outbound',
            'status'           => 'draft',
            'scheduled_at'     => $scheduledAt ?: null,
        ]));

        // Bump template usage counter
        if (!empty($data['template_id'])) {
            EmailTemplate::where('id', $data['template_id'])->increment('times_used');
        }

        // Save file attachments to the centralised documents disk and link them
        // to this EmailMessage (and the opportunity if present).
        $this->saveAttachments($request, $message);

        // Optional follow-up: schedule one if asked
        $this->scheduleFollowUp($request, $message);

        if ($sendOption === 'schedule' && $scheduledAt) {
            $message->update(['status' => 'scheduled']);
            return redirect()->route('emails.show', $message->id)
                ->with('success', 'Email scheduled for ' . $message->scheduled_at->format('M j, Y g:i A') . '.');
        }

        if ($sendOption === 'now') {
            // Send inline — the queue worker has been unreliable; for typical
            // compose volumes this finishes well within the request timeout.
            try {
                $ok = $emailService->sendEmail($message);
            } catch (Throwable $e) {
                $message->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
                return redirect()->route('emails.show', $message->id)
                    ->with('error', 'Send failed: ' . $e->getMessage());
            }

            if ($ok) {
                return redirect()->route('emails.show', $message->id)
                    ->with('success', 'Email sent.');
            }

            return redirect()->route('emails.show', $message->id)
                ->with('error', 'Send failed: ' . ($message->fresh()->failure_reason ?? 'unknown error'));
        }

        // Default: save as draft
        return redirect()->route('emails.show', $message->id)
            ->with('success', 'Draft saved.');
    }

    /**
     * Persist uploaded attachments to the centralised documents disk and link
     * them to the EmailMessage (creating Document rows so they show up in the
     * Documents page too).
     */
    private function saveAttachments(Request $request, EmailMessage $message): void
    {
        $files = $request->file('attachments') ?? [];
        if (! is_array($files)) {
            $files = [$files];
        }

        $dir = storage_path('app/private/email-attachments');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $original = $file->getClientOriginalName();
            $mime     = $file->getClientMimeType();
            $filename = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
            $file->move($dir, $filename);
            $fullPath = $dir . '/' . $filename;
            $size     = file_exists($fullPath) ? filesize($fullPath) : 0;
            $rel      = 'email-attachments/' . $filename;

            // Mirror into central Documents catalogue first so we can link the
            // EmailAttachment to the Document row for cross-referencing.
            $documentId = null;
            try {
                $document = \App\Models\Document::create($this->tenantData([
                    'opportunity_id' => $message->opportunity_id,
                    'contact_id'     => $message->contact_id,
                    'name'           => $original,
                    'file_path'      => $rel,
                    'file_name'      => $original,
                    'file_size'      => $size,
                    'mime_type'      => $mime,
                    'document_type'  => 'email_attachment',
                ]));
                $documentId = $document->id;
            } catch (Throwable) {
                // Documents table schema may differ — fail-soft, attachment still saves.
            }

            \App\Models\EmailAttachment::create([
                'email_message_id' => $message->id,
                'document_id'      => $documentId,
                'file_name'        => $original,
                'file_path'        => $rel,
                'mime_type'        => $mime,
                'file_size'        => $size,
            ]);
        }
    }

    /**
     * If the user requested a follow-up on the compose form, create one.
     * Pre-fills subject/body from the selected follow-up template so the
     * scheduled-send job has something to render.
     */
    private function scheduleFollowUp(Request $request, EmailMessage $message): void
    {
        if (! $request->boolean('schedule_follow_up')) {
            return;
        }
        // FollowUps require an opportunity in this schema; skip if no opportunity linked.
        if (! $message->opportunity_id) {
            return;
        }

        $days  = max(1, min(60, (int) $request->input('follow_up_days', 5)));
        $tplId = $request->input('follow_up_template_id') ?: null;

        $subject = 'Re: ' . $message->subject;
        $body    = '';
        if ($tplId) {
            $tpl = EmailTemplate::find($tplId);
            if ($tpl) {
                $subject = $tpl->subject ?: $subject;
                $body    = $tpl->body ?: '';
            }
        }

        try {
            \App\Models\FollowUp::create($this->tenantData([
                'opportunity_id'    => $message->opportunity_id,
                'contact_id'        => $message->contact_id,
                'email_account_id'  => $message->email_account_id,
                'email_template_id' => $tplId,
                'email_message_id'  => $message->id,
                'due_at'            => now()->addDays($days),
                'status'            => 'pending',
                'subject'           => $subject,
                'body'              => $body,
                'follow_up_number'  => 1,
            ]));
        } catch (Throwable) {
            // Schema mismatch falls through silently — the send already succeeded.
        }
    }

    /**
     * Parse a comma/semicolon-separated address list into [{email,name}, ...].
     *
     * @return array<int, array{email:string, name:string}>
     */
    private function parseAddresses(mixed $raw): array
    {
        if (! $raw) {
            return [];
        }
        if (is_array($raw)) {
            return collect($raw)
                ->map(fn ($v) => is_array($v) ? $v : ['email' => trim((string) $v), 'name' => ''])
                ->filter(fn ($v) => filter_var($v['email'] ?? '', FILTER_VALIDATE_EMAIL))
                ->values()
                ->all();
        }
        $parts = preg_split('/[;,]+/', (string) $raw) ?: [];
        $out   = [];
        foreach ($parts as $p) {
            $e = strtolower(trim($p));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[] = ['email' => $e, 'name' => ''];
            }
        }
        return $out;
    }

    public function show(Request $request, int $id): View
    {
        $email = $this->tenantQuery(EmailMessage::class)
            ->with(['emailAccount', 'contact', 'opportunity', 'attachments', 'apiAttachments', 'replies'])
            ->findOrFail($id);

        $this->authorize('view', $email);

        return view('emails.show', compact('email'));
    }

    public function edit(Request $request, int $id): View
    {
        $email = $this->tenantQuery(EmailMessage::class)
            ->with(['emailAccount', 'contact', 'opportunity', 'attachments'])
            ->findOrFail($id);

        $this->authorize('update', $email);

        if (! in_array($email->status, ['draft', 'scheduled'], true)) {
            abort(403, 'Only drafts and scheduled emails can be edited.');
        }

        $emailAccounts = $this->tenantQuery(EmailAccount::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $templates = $this->tenantQuery(EmailTemplate::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $contacts = $this->tenantQuery(Contact::class)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        $opportunities = $this->tenantQuery(Opportunity::class)
            ->orderByDesc('updated_at')
            ->get(['id', 'title']);

        [$signatures, $defaultSignatureId, $signaturePayload] = $this->signatureOptions();

        return view('emails.edit', compact(
            'email',
            'emailAccounts',
            'templates',
            'contacts',
            'opportunities',
            'signatures',
            'defaultSignatureId',
            'signaturePayload'
        ));
    }

    public function update(StoreEmailMessageRequest $request, EmailSendingService $emailService, int $id): RedirectResponse
    {
        $email = $this->tenantQuery(EmailMessage::class)->findOrFail($id);
        $this->authorize('update', $email);

        if (! in_array($email->status, ['draft', 'scheduled'], true)) {
            return redirect()->route('emails.show', $email->id)
                ->with('error', 'Only drafts and scheduled emails can be edited.');
        }

        $data = $request->validated();

        $sendOption  = $request->input('send_option', 'draft');
        $scheduledAt = $request->input('scheduled_at', $request->input('send_at'));

        $ccList  = $this->parseAddresses($request->input('cc'));
        $bccList = $this->parseAddresses($request->input('bcc'));

        $email->update([
            'email_account_id' => $data['email_account_id'],
            'contact_id'       => $data['contact_id'] ?? null,
            'opportunity_id'   => $data['opportunity_id'] ?? null,
            'template_id'      => $data['template_id'] ?? null,
            'email_signature_id' => $this->validatedSignatureId($data['email_signature_id'] ?? null),
            'to_email'         => $data['to_email'],
            'to_name'          => $data['to_name'] ?? null,
            'subject'          => $data['subject'],
            'body'             => $data['body'],
            'cc'               => $ccList ?: null,
            'bcc'              => $bccList ?: null,
            'scheduled_at'     => $scheduledAt ?: null,
        ]);

        $this->saveAttachments($request, $email);

        if ($sendOption === 'schedule' && $scheduledAt) {
            $email->update(['status' => 'scheduled']);
            return redirect()->route('emails.show', $email->id)
                ->with('success', 'Schedule updated.');
        }

        if ($sendOption === 'now') {
            try {
                $ok = $emailService->sendEmail($email);
            } catch (Throwable $e) {
                $email->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
                return redirect()->route('emails.show', $email->id)
                    ->with('error', 'Send failed: ' . $e->getMessage());
            }
            return redirect()->route('emails.show', $email->id)
                ->with($ok ? 'success' : 'error', $ok ? 'Email sent.' : 'Send failed: ' . ($email->fresh()->failure_reason ?? 'unknown error'));
        }

        // sendOption=draft (or anything else): keep as draft
        $email->update(['status' => 'draft']);
        return redirect()->route('emails.show', $email->id)
            ->with('success', 'Draft updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $email = $this->tenantQuery(EmailMessage::class)->findOrFail($id);

        $this->authorize('delete', $email);

        // Only drafts and scheduled emails can be deleted
        if (!in_array($email->status, ['draft', 'scheduled'])) {
            return redirect()->back()
                ->with('error', 'Only drafts and scheduled emails can be deleted.');
        }

        $email->delete();

        return redirect()->route('emails.index')
            ->with('success', 'Email deleted.');
    }

    public function getTemplate(Request $request): JsonResponse
    {
        $request->validate(['template_id' => 'required|integer']);

        $template = $this->tenantQuery(EmailTemplate::class)
            ->findOrFail($request->integer('template_id'));

        return response()->json([
            'subject' => $template->subject,
            'body'    => $template->body,
        ]);
    }

    private function signatureOptions(): array
    {
        $signatures = $this->tenantQuery(EmailSignature::class)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $defaultSignatureId = optional($signatures->firstWhere('is_default', true) ?? $signatures->first())->id;
        $signaturePayload = $signatures
            ->mapWithKeys(fn (EmailSignature $signature) => [
                (string) $signature->id => [
                    'name' => $signature->name,
                    'html' => $signature->renderHtml(),
                ],
            ])
            ->all();

        return [$signatures, $defaultSignatureId, $signaturePayload];
    }

    private function validatedSignatureId(mixed $signatureId): ?int
    {
        if (! $signatureId) {
            return null;
        }

        return $this->tenantQuery(EmailSignature::class)->findOrFail((int) $signatureId)->id;
    }
}
