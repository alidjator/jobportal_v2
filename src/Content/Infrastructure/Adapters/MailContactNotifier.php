<?php

namespace Src\Content\Infrastructure\Adapters;

use Illuminate\Support\Str;
use Src\Content\Domain\ContactNotifier;
use Src\Content\Infrastructure\Persistence\Eloquent\Admin;
use Throwable;

/** Kirim email ke semua admin (seperti ContactService lama), hanya jika konfigurasi mail lengkap. Best-effort. */
final class MailContactNotifier implements ContactNotifier
{
    public function notifyAdmins(array $contact): void
    {
        if (! $this->mailConfigured()) {
            return;
        }

        $contact['token'] = Str::random(12);

        try {
            Admin::all()->each(fn (Admin $admin) => $admin->notify(new ContactNotification($contact)));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function mailConfigured(): bool
    {
        $mailer = config('mail.default');
        $smtp = (array) config('mail.mailers.smtp');

        if ($mailer !== 'smtp') {
            return (bool) config('mail.from.address') && (bool) config('mail.from.name');
        }

        return ! empty($smtp['host']) && ! empty($smtp['port']) && ! empty($smtp['username'])
            && ! empty($smtp['password']) && config('mail.from.address') && config('mail.from.name');
    }
}
