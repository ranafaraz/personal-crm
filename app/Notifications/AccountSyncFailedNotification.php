<?php

namespace App\Notifications;

use App\Models\EmailAccount;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSyncFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'account_sync_failed';

    public function __construct(
        public readonly EmailAccount $emailAccount,
        public readonly string $error = '',
    ) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)->enabledChannels($notifiable, self::TYPE);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'             => self::TYPE,
            'title'            => 'Inbox Sync Failed',
            'body'             => "Failed to sync inbox for {$this->emailAccount->name} ({$this->emailAccount->email})." . ($this->error ? " {$this->error}" : ''),
            'icon'             => 'exclamation',
            'action_url'       => route('email-accounts.edit', $this->emailAccount->id),
            'action_label'     => 'Fix Account',
            'email_account_id' => $this->emailAccount->id,
            'account_name'     => $this->emailAccount->name,
            'account_email'    => $this->emailAccount->email,
            'error'            => $this->error,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Inbox Sync Failed: ' . $this->emailAccount->name)
            ->error()
            ->greeting('Inbox sync failed!')
            ->line("Account: {$this->emailAccount->name} ({$this->emailAccount->email})")
            ->when($this->error, fn ($m) => $m->line("Error: {$this->error}"))
            ->action('Fix Account', route('email-accounts.edit', $this->emailAccount->id));
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title'   => 'Inbox Sync Failed',
            'message' => $this->emailAccount->name,
            'url'     => route('email-accounts.edit', $this->emailAccount->id),
        ];
    }
}
