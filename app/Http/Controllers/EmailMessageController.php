<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmailMessageRequest;
use App\Jobs\SendEmailJob;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailTemplate;
use App\Models\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailMessageController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->tenantQuery(EmailMessage::class)
            ->with(['emailAccount', 'contact', 'opportunity']);

        // Tab: inbox or outbox
        $tab = $request->input('tab', 'outbox');
        $query->where('direction', $tab === 'inbox' ? 'inbound' : 'outbound');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($accountId = $request->input('email_account_id')) {
            $query->where('email_account_id', $accountId);
        }

        $emails = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $emailAccounts = $this->tenantQuery(EmailAccount::class)
            ->orderBy('name')
            ->get();

        return view('emails.index', compact('emails', 'tab', 'emailAccounts'));
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

        return view('emails.compose', compact('emailAccounts', 'templates', 'contacts', 'opportunities'));
    }

    public function store(StoreEmailMessageRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $message = EmailMessage::create($this->tenantData([
            'email_account_id' => $data['email_account_id'],
            'contact_id'       => $data['contact_id'] ?? null,
            'opportunity_id'   => $data['opportunity_id'] ?? null,
            'template_id'      => $data['template_id'] ?? null,
            'to_email'         => $data['to_email'],
            'to_name'          => $data['to_name'] ?? null,
            'subject'          => $data['subject'],
            'body'             => $data['body'],
            'cc'               => $data['cc'] ?? null,
            'bcc'              => $data['bcc'] ?? null,
            'direction'        => 'outbound',
            'status'           => 'draft',
            'scheduled_at'     => $data['send_at'] ?? null,
        ]));

        // Increment template usage counter if a template was used
        if (!empty($data['template_id'])) {
            EmailTemplate::where('id', $data['template_id'])->increment('times_used');
        }

        if (!empty($data['send_at'])) {
            // Schedule for later
            $message->update(['status' => 'scheduled']);

            return redirect()->route('emails.show', $message->id)
                ->with('success', 'Email scheduled for ' . $message->scheduled_at->format('M j, Y g:i A') . '.');
        }

        if ($request->boolean('send_now')) {
            // Dispatch immediately
            $message->update(['status' => 'queued']);
            SendEmailJob::dispatch($message);

            return redirect()->route('emails.index')
                ->with('success', 'Email queued for sending.');
        }

        // Save as draft
        return redirect()->route('emails.show', $message->id)
            ->with('success', 'Draft saved.');
    }

    public function show(Request $request, int $id): View
    {
        $email = $this->tenantQuery(EmailMessage::class)
            ->with(['emailAccount', 'contact', 'opportunity', 'attachments', 'replies'])
            ->findOrFail($id);

        $this->authorize('view', $email);

        return view('emails.show', compact('email'));
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
}
