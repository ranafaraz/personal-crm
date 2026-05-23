<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmailAccountRequest;
use App\Http\Requests\UpdateEmailAccountRequest;
use App\Jobs\SyncInboxJob;
use App\Models\EmailAccount;
use App\Services\EmailSendingService;
use App\Services\ImapSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailAccountController extends Controller
{
    public function __construct(
        private EmailSendingService $emailSendingService,
        private ImapSyncService $imapSyncService,
    ) {}

    public function index(Request $request): View
    {
        $accounts = $this->tenantQuery(EmailAccount::class)
            ->withCount('emailMessages')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('email-accounts.index', compact('accounts'));
    }

    public function create(): View
    {
        return view('email-accounts.create');
    }

    public function store(StoreEmailAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['imap_host'] ??= '';
        $data['imap_port'] ??= 993;
        $data['imap_encryption'] ??= 'ssl';
        $data['imap_username'] ??= $data['email'];
        $data['imap_password'] ??= $data['smtp_password'];
        $data['daily_limit'] ??= 50;
        $data['hourly_limit'] ??= 10;
        $data['min_delay_seconds'] ??= 30;

        EmailAccount::create($this->tenantData($data));

        return redirect()->route('email-accounts.index')
            ->with('success', 'Email account created successfully.');
    }

    public function show(Request $request, int $id): View
    {
        $account = $this->tenantQuery(EmailAccount::class)->findOrFail($id);

        $this->authorize('view', $account);

        $sentEmails = $account->emailMessages()
            ->where('direction', 'outbound')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('email-accounts.show', compact('account', 'sentEmails'));
    }

    public function edit(Request $request, int $id): View
    {
        $account = $this->tenantQuery(EmailAccount::class)->findOrFail($id);

        $this->authorize('update', $account);

        // Never populate password fields – pass account without password data
        return view('email-accounts.edit', compact('account'));
    }

    public function update(UpdateEmailAccountRequest $request, int $id): RedirectResponse
    {
        $account = $this->tenantQuery(EmailAccount::class)->findOrFail($id);

        $this->authorize('update', $account);

        $data = $request->validated();

        // Only update passwords if new values were provided
        if (empty($data['smtp_password'])) {
            unset($data['smtp_password']);
        }
        if (empty($data['imap_password'])) {
            unset($data['imap_password']);
        }

        $account->update($data);

        return redirect()->route('email-accounts.show', $account->id)
            ->with('success', 'Email account updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $account = $this->tenantQuery(EmailAccount::class)->findOrFail($id);

        $this->authorize('delete', $account);

        $account->delete();

        return redirect()->route('email-accounts.index')
            ->with('success', 'Email account deleted.');
    }

    public function testSmtp(Request $request, int $id): JsonResponse
    {
        $account = $this->tenantQuery(EmailAccount::class)->findOrFail($id);

        $this->authorize('view', $account);

        $result = $this->emailSendingService->testSmtpConnection($account);

        return response()->json($result);
    }

    public function testImap(Request $request, int $id): JsonResponse
    {
        $account = $this->tenantQuery(EmailAccount::class)->findOrFail($id);

        $this->authorize('view', $account);

        $result = $this->imapSyncService->testImapConnection($account);

        return response()->json($result);
    }

    public function syncInbox(Request $request, int $id): RedirectResponse
    {
        $account = $this->tenantQuery(EmailAccount::class)->findOrFail($id);

        $this->authorize('update', $account);

        SyncInboxJob::dispatch($account);

        return redirect()->route('email-accounts.show', $account->id)
            ->with('success', 'Inbox sync queued.');
    }
}
