<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\Opportunity;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmailSendingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetupDemoDataCommand extends Command
{
    protected $signature   = 'crm:setup-demo';
    protected $description = 'Add crm@dexdevs.com email account, demo opportunity, demo contact, and send a test email';

    public function handle(EmailSendingService $emailService): int
    {
        $tenant = Tenant::where('slug', 'default')->firstOrFail();
        $user   = User::where('email', 'ranafarazahmed@gmail.com')->firstOrFail();

        // ── 1. crm@dexdevs.com email account ─────────────────────────────────
        $crmAccount = EmailAccount::firstOrCreate(
            ['user_id' => $user->id, 'email' => 'crm@dexdevs.com'],
            [
                'tenant_id'         => $tenant->id,
                'name'              => 'DEXDevs CRM Mail',
                'from_name'         => 'DEXDevs CRM',
                'smtp_host'         => 'mail.dexdevs.com',
                'smtp_port'         => 587,
                'smtp_encryption'   => 'tls',
                'smtp_username'     => 'crm@dexdevs.com',
                'smtp_password'     => 'dexdevs007',
                'imap_host'         => 'mail.dexdevs.com',
                'imap_port'         => 993,
                'imap_encryption'   => 'ssl',
                'imap_username'     => 'crm@dexdevs.com',
                'imap_password'     => 'dexdevs007',
                'daily_limit'       => 200,
                'hourly_limit'      => 30,
                'min_delay_seconds' => 15,
                'is_active'         => true,
                'is_default'        => false,
                'notes'             => 'Self-hosted dexdevs.com mail server (mail.dexdevs.com)',
            ]
        );
        $this->info($crmAccount->wasRecentlyCreated
            ? "Created email account: {$crmAccount->email} (ID {$crmAccount->id})"
            : "Email account already exists: {$crmAccount->email} (ID {$crmAccount->id})");

        // ── 2. Demo tag ───────────────────────────────────────────────────────
        $tag = Tag::firstOrCreate(
            ['slug' => 'demo', 'tenant_id' => $tenant->id],
            ['user_id' => $user->id, 'name' => 'Demo', 'color' => 'indigo']
        );

        // ── 3. Demo contact (dexdevs007@gmail.com) ────────────────────────────
        $contact = Contact::firstOrCreate(
            ['email' => 'dexdevs007@gmail.com', 'user_id' => $user->id],
            [
                'tenant_id' => $tenant->id,
                'first_name' => 'DEXDevs',
                'last_name'  => 'Demo',
                'company'    => 'DEXDevs',
                'industry'   => 'Technology',
                'job_title'  => 'Software Engineer',
                'notes'      => 'Demo contact used for end-to-end email testing.',
                'status'     => 'active',
                'source'     => 'internal',
            ]
        );
        $contact->tags()->syncWithoutDetaching([$tag->id]);
        $this->info($contact->wasRecentlyCreated
            ? "Created demo contact: {$contact->email}"
            : "Demo contact already exists: {$contact->email}");

        // ── 4. Demo opportunity ───────────────────────────────────────────────
        $opportunity = Opportunity::firstOrCreate(
            ['title' => 'Demo Job Opportunity - Full Stack Developer', 'user_id' => $user->id],
            [
                'tenant_id'        => $tenant->id,
                'type'             => 'job',
                'organization'     => 'DEXDevs',
                'description'      => 'This is a demo opportunity created for testing the end-to-end CRM email workflow.',
                'status'           => 'active',
                'priority'         => 'medium',
                'last_activity_at' => now(),
                'notes'            => 'Demo opportunity for testing email/CRM integration.',
            ]
        );
        $opportunity->tags()->syncWithoutDetaching([$tag->id]);
        $opportunity->contacts()->syncWithoutDetaching([$contact->id]);
        $this->info($opportunity->wasRecentlyCreated
            ? "Created demo opportunity: {$opportunity->title}"
            : "Demo opportunity already exists (ID {$opportunity->id})");

        // ── 5. Test SMTP on Personal Gmail ────────────────────────────────────
        $gmailAccount = EmailAccount::where('user_id', $user->id)
            ->where('email', 'ranafarazahmed@gmail.com')
            ->first();

        if ($gmailAccount) {
            $this->newLine();
            $this->line("Testing SMTP on {$gmailAccount->email}...");
            $smtpResult = $emailService->testSmtpConnection($gmailAccount);
            $smtpResult['success']
                ? $this->info("  ✓ SMTP OK: " . $smtpResult['message'])
                : $this->error("  ✗ SMTP FAIL: " . $smtpResult['message']);

            if ($smtpResult['success']) {
                // Send test email from personal Gmail to dexdevs007@gmail.com
                $this->line("  Sending test email → dexdevs007@gmail.com...");
                $message = EmailMessage::create([
                    'tenant_id'        => $tenant->id,
                    'user_id'          => $user->id,
                    'email_account_id' => $gmailAccount->id,
                    'contact_id'       => $contact->id,
                    'opportunity_id'   => $opportunity->id,
                    'to_email'         => 'dexdevs007@gmail.com',
                    'to_name'          => 'DEXDevs Demo',
                    'subject'          => 'Test Email from Personal CRM — Gmail ✓',
                    'body'             => "CRM Email Test\n\nThis test email was sent from your Personal CRM using ranafarazahmed@gmail.com via SMTP.\nIf you received this, your Gmail SMTP integration is working end-to-end.",
                    'direction'        => 'outbound',
                    'status'           => 'queued',
                ]);

                $sent = $emailService->sendEmail($message);
                $sent
                    ? $this->info("  ✓ Test email sent to dexdevs007@gmail.com")
                    : $this->error("  ✗ Failed to send test email");
            }
        }

        // ── 6. Test SMTP on crm@dexdevs.com ──────────────────────────────────
        $this->newLine();
        $this->line("Testing SMTP on crm@dexdevs.com...");
        $smtpCrm = $emailService->testSmtpConnection($crmAccount);
        $smtpCrm['success']
            ? $this->info("  ✓ SMTP OK: " . $smtpCrm['message'])
            : $this->error("  ✗ SMTP FAIL: " . $smtpCrm['message']);

        if ($smtpCrm['success']) {
            $this->line("  Sending test email from crm@dexdevs.com → ranafarazahmed@gmail.com...");
            $msg2 = EmailMessage::create([
                'tenant_id'        => $tenant->id,
                'user_id'          => $user->id,
                'email_account_id' => $crmAccount->id,
                'contact_id'       => $contact->id,
                'opportunity_id'   => $opportunity->id,
                'to_email'         => 'ranafarazahmed@gmail.com',
                'to_name'          => 'Rana Faraz',
                'subject'          => 'Test Email from CRM — dexdevs.com Mail Server ✓',
                'body'             => "CRM Mail Server Test\n\nThis test email was sent from your self-hosted mail server at mail.dexdevs.com using crm@dexdevs.com.\nSSL/TLS is now using a valid Let's Encrypt certificate.",
                'direction'        => 'outbound',
                'status'           => 'queued',
            ]);

            $sent2 = $emailService->sendEmail($msg2);
            $sent2
                ? $this->info("  ✓ Test email sent from crm@dexdevs.com to ranafarazahmed@gmail.com")
                : $this->error("  ✗ Failed to send from crm@dexdevs.com");
        }

        $this->newLine();
        $this->info('Demo setup complete. Login at http://localhost:8080/login');
        return self::SUCCESS;
    }
}
