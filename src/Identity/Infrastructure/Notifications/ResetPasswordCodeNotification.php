<?php

namespace Src\Identity\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordCodeNotification extends Notification
{
    public function __construct(public readonly string $code) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Reset')
            ->greeting('Hello there,')
            ->line('Your reset password code: '.$this->code)
            ->line('Thank you for using our application!');
    }
}
