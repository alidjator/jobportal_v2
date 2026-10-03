<?php

namespace Src\Identity\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email sambutan / persetujuan pending. Subjek dan isi berasal dari tabel `email_templates`
 * (disederhanakan: tanpa view Blade `mails.email-template` aplikasi lama).
 */
class AccountMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $subject, public readonly string $body) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->line($this->body)
            ->action('Login', url('login'));
    }
}
