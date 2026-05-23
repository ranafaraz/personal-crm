<?php

namespace App\Notifications;

use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailySummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'daily_summary';

    public function __construct(public readonly array $summary) {}

    public function via(object $notifiable): array
    {
        return app(NotificationPreferenceService::class)->enabledChannels($notifiable, self::TYPE);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'               => self::TYPE,
            'title'              => 'Daily Summary',
            'body'               => $this->buildBodyText(),
            'icon'               => 'chart-bar',
            'action_url'         => route('dashboard'),
            'action_label'       => 'View Dashboard',
            'emails_sent'        => $this->summary['emails_sent'] ?? 0,
            'replies_received'   => $this->summary['replies_received'] ?? 0,
            'follow_ups_due'     => $this->summary['follow_ups_due'] ?? 0,
            'positive_replies'   => $this->summary['positive_replies'] ?? 0,
            'failed_sends'       => $this->summary['failed_sends'] ?? 0,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your Daily Outreach Summary — ' . now()->format('M d, Y'))
            ->greeting('Good morning!')
            ->line("Here's your outreach summary for today:");

        foreach ($this->buildLines() as $line) {
            $mail->line($line);
        }

        return $mail->action('View Dashboard', route('dashboard'));
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title'   => 'Daily Summary',
            'message' => $this->buildBodyText(),
            'url'     => route('dashboard'),
        ];
    }

    private function buildBodyText(): string
    {
        $parts = [];
        if (($v = $this->summary['emails_sent'] ?? 0) > 0)      { $parts[] = "{$v} sent"; }
        if (($v = $this->summary['replies_received'] ?? 0) > 0)  { $parts[] = "{$v} replies"; }
        if (($v = $this->summary['follow_ups_due'] ?? 0) > 0)    { $parts[] = "{$v} follow-ups due"; }
        if (($v = $this->summary['positive_replies'] ?? 0) > 0)  { $parts[] = "{$v} positive"; }

        return empty($parts) ? 'No activity today.' : implode(', ', $parts) . '.';
    }

    private function buildLines(): array
    {
        return [
            '📧 Emails sent: '       . ($this->summary['emails_sent'] ?? 0),
            '💬 Replies received: '  . ($this->summary['replies_received'] ?? 0),
            '✅ Positive replies: '  . ($this->summary['positive_replies'] ?? 0),
            '⏰ Follow-ups due: '    . ($this->summary['follow_ups_due'] ?? 0),
            '❌ Failed sends: '      . ($this->summary['failed_sends'] ?? 0),
        ];
    }
}
