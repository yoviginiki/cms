<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** F09 — the reset link that was previously only a commented-out Mail call. */
class PasswordResetLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $email)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/') . '/admin/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $this->email,
        ]);

        return (new MailMessage())
            ->subject('Reset your password')
            ->line('We received a request to reset the password for this account.')
            ->action('Reset password', $url)
            ->line('The link is valid for 60 minutes and can be used once.')
            ->line('If you did not request a reset, no action is needed.');
    }
}
