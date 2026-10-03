<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Notifications;

use Illuminate\Support\Facades\Notification;
use Src\Recruitment\Application\JobManagement\Ports\JobNotifier;
use Src\Recruitment\Domain\JobManagement\JobMessages;

/**
 * Notifikasi database (tabel `notifications`) lewat sistem notifikasi Laravel, dengan tipe & isi
 * seperti aplikasi lama. Admin hanya diberi tahu bila konfigurasi email lengkap (seperti `checkMailConfig()`).
 */
class DatabaseJobNotifier implements JobNotifier
{
    public function jobCreated(int $ownerUserId, array $job): void
    {
        $title = $job['status'] === 'active'
            ? JobMessages::get('job_has_been_created_and_published')
            : JobMessages::get('job_has_been_created_and_waiting_for_admin_approval');

        $this->toOwner($ownerUserId, new JobNotification(
            'App\Notifications\Website\Company\JobCreatedNotification', $title, url('job/'.$job['slug']),
        ));

        $this->toAdmins(new JobNotification(
            'App\Notifications\Admin\NewJobAvailableNotification',
            'A new job is available for approval',
            url('admin/job').'?'.http_build_query(['title' => $job['title']]),
        ));
    }

    public function jobEditWaitingApproval(int $ownerUserId, array $job): void
    {
        $this->toOwner($ownerUserId, new JobNotification(
            'App\Notifications\Website\Company\EditApproveNotification',
            'Your job has been edited and waiting for admin approval your changes.',
            url('job/'.$job['slug']),
        ));

        $this->toAdmins(new JobNotification(
            'App\Notifications\Admin\NewEditedJobAvailableNotification',
            __('A new edited job is available for approval'),
            url('edited/job/list/'.rawurlencode($job['title'])),
        ));
    }

    private function toOwner(int $userId, JobNotification $notification): void
    {
        $user = NotifiableUser::query()->find($userId);
        if ($user !== null) {
            Notification::send($user, $notification);
        }
    }

    private function toAdmins(JobNotification $notification): void
    {
        if (! $this->mailConfigured()) {
            return;
        }

        $admins = NotifiableAdmin::query()->get();
        Notification::send($admins, $notification);
    }

    private function mailConfigured(): bool
    {
        return (bool) (config('mail.mailers.smtp.transport') && config('mail.mailers.smtp.host')
            && config('mail.mailers.smtp.port') && config('mail.mailers.smtp.username')
            && config('mail.mailers.smtp.password') && config('mail.mailers.smtp.encryption')
            && config('mail.from.address') && config('mail.from.name'));
    }
}
