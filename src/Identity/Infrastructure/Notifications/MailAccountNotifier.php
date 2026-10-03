<?php

namespace Src\Identity\Infrastructure\Notifications;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Src\Identity\Domain\AccountNotifier;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Throwable;

/**
 * Notifikasi admin (tabel `notifications`, channel database seperti aplikasi lama) dan email
 * sambutan / reset password. Semua best-effort: kegagalan hanya dilaporkan ke log.
 */
class MailAccountNotifier implements AccountNotifier
{
    public function userRegistered(array $user): void
    {
        $this->attempt(fn () => $this->notifyAdmins($user));

        if (! $this->mailConfigured()) {
            return;
        }

        $this->attempt(fn () => $this->sendWelcomeMail($user));
    }

    public function resetCodeIssued(int $userId, string $code): void
    {
        if (! $this->mailConfigured()) {
            return;
        }

        $this->attempt(fn () => UserModel::query()->find($userId)?->notify(new ResetPasswordCodeNotification($code)));
    }

    /** @param  array<string,mixed>  $user */
    private function notifyAdmins(array $user): void
    {
        // Tabel `admins` (milik panel admin) dibaca read-only; notifikasi ditulis ke tabel `notifications`
        // dengan notifiable_type lama agar tampil di panel admin.
        $profileTable = $user['role'] === 'company' ? 'companies' : 'candidates';
        $profileId = DB::table($profileTable)->where('user_id', $user['id'])->value('id');
        $path = $user['role'] === 'company' ? 'company' : 'candidate';
        $now = now();

        $rows = DB::table('admins')->pluck('id')->map(fn ($adminId) => [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Admin\\NewUserRegisteredNotification',
            'notifiable_type' => 'App\\Models\\Admin',
            'notifiable_id' => $adminId,
            'data' => json_encode([
                'title' => 'A '.ucfirst($user['role']).' registered recently',
                'url' => url("admin/{$path}/{$profileId}"),
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('notifications')->insert($rows);
    }

    /** @param  array<string,mixed>  $user */
    private function sendWelcomeMail(array $user): void
    {
        $isCandidate = $user['role'] === 'candidate';
        $autoActivated = (bool) DB::table('settings')->orderBy('id')->value(
            $isCandidate ? 'candidate_account_auto_activation' : 'employer_auto_activation',
        );

        $type = match (true) {
            $isCandidate && $autoActivated => 'new_candidate',
            $isCandidate => 'new_pending_candidate',
            $autoActivated => 'new_company',
            default => 'new_company_pending',
        };

        $template = DB::table('email_templates')->where('type', $type)->first();
        $replace = [
            '{user_name}' => $user['name'],
            '{user_email}' => $user['email'],
            // Password tidak lagi dikirim lewat email (aplikasi lama mengirim password polos).
            '{user_password}' => '********',
        ];

        $subject = html_entity_decode(strtr($template->subject ?? '', $replace));
        $message = html_entity_decode(strtr($template->message ?? '', $replace));

        Notification::route('mail', $user['email'])->notify(new AccountMailNotification(
            $subject !== '' ? $subject : 'Welcome to '.config('app.name'),
            $message !== '' ? $message : 'Hello '.$user['name'].', your account has been '.($autoActivated ? 'created.' : 'created and is waiting for approval.'),
        ));
    }

    /** Padanan `checkMailConfig()` aplikasi lama. */
    private function mailConfigured(): bool
    {
        return (bool) (config('mail.mailers.smtp.transport')
            && config('mail.mailers.smtp.host')
            && config('mail.mailers.smtp.port')
            && config('mail.mailers.smtp.username')
            && config('mail.mailers.smtp.password')
            && config('mail.mailers.smtp.encryption')
            && config('mail.from.address')
            && config('mail.from.name'));
    }

    private function attempt(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
