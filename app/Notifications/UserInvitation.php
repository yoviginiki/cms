<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** F10 — the invitation email (the old flow only showed the link to the admin). */
class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $invitedBy, public string $expiresAt)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/') . '/admin/invite/' . $this->token;

        return (new MailMessage())
            ->subject('You have been invited')
            ->line("{$this->invitedBy} invited you to the CMS.")
            ->action('Accept invitation', $url)
            ->line("The invitation expires on {$this->expiresAt}.");
    }
}
