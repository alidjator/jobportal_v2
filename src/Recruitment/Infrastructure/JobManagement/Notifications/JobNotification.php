<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Notifications;

use Illuminate\Notifications\Notification;

/** Notifikasi database sederhana `{title, url}`; `type` meniru nama kelas aplikasi lama. */
final class JobNotification extends Notification
{
    public function __construct(
        private readonly string $legacyType,
        private readonly string $title,
        private readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->legacyType;
    }

    /** @return array{title: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'url' => $this->url];
    }
}
