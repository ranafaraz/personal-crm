<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailSendingService;
use App\Services\ImapSyncService;
use Illuminate\Console\Command;

class TestEmailCommand extends Command
{
    protected $signature   = 'crm:test-email {account_id?}';
    protected $description = 'Test SMTP and IMAP for an email account, then trigger inbox sync';

    public function handle(EmailSendingService $smtp, ImapSyncService $imap): int
    {
        $id      = $this->argument('account_id') ?? 1;
        $account = EmailAccount::find($id);

        if (!$account) {
            $this->error("Email account {$id} not found.");
            return self::FAILURE;
        }

        $this->info("Testing account: {$account->name} <{$account->email}>");

        // SMTP test
        $this->line('  → Testing SMTP…');
        $smtpResult = $smtp->testSmtpConnection($account);
        if ($smtpResult['success']) {
            $this->info('  ✓ SMTP OK: ' . $smtpResult['message']);
        } else {
            $this->error('  ✗ SMTP FAIL: ' . $smtpResult['message']);
        }

        // IMAP test
        $this->line('  → Testing IMAP…');
        $imapResult = $imap->testImapConnection($account);
        if ($imapResult['success']) {
            $this->info('  ✓ IMAP OK: ' . $imapResult['message']);
        } else {
            $this->error('  ✗ IMAP FAIL: ' . $imapResult['message']);
        }

        // Inbox sync if IMAP works
        if ($imapResult['success']) {
            $this->line('  → Syncing inbox (last 7 days)…');
            $stats = $imap->syncAccount($account);
            $this->info("  ✓ Synced {$stats['synced']} messages, matched {$stats['matched']}");
            if (!empty($stats['errors'])) {
                foreach ($stats['errors'] as $err) {
                    $this->warn("    ! {$err}");
                }
            }
        }

        return self::SUCCESS;
    }
}
