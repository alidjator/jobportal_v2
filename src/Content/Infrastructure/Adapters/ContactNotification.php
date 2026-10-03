<?php

namespace Src\Content\Infrastructure\Adapters;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ContactNotification extends Notification
{
    /** @param  array{name: string, email: string, subject: string, message: string}  $contact */
    public function __construct(private array $contact) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->contact['subject'])
            ->greeting('Hello Admin')
            ->line('Name : '.$this->contact['name'])
            ->line('Email : '.$this->contact['email'])
            ->line('Message : '.$this->contact['message'])
            ->line('Regards,')
            ->salutation(config('app.name'));
    }
}
