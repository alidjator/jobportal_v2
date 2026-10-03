<?php

namespace Src\Recruitment\Infrastructure\JobBoard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Recruitment\Domain\JobBoard\JobActivityNotifier;
use Src\Recruitment\Infrastructure\JobBoard\Persistence\QueryCandidateJobActivity;
use Throwable;

/**
 * Menulis notifikasi database (tabel `notifications`, `type` dan payload sama dengan
 * `ApplyJobNotification` / `BookmarkJobNotification` aplikasi lama) untuk user perusahaan,
 * serta untuk kandidat bila `recent_activities_alert` aktif. Best-effort: error hanya dilaporkan ke log.
 * (Email konfirmasi lamaran aplikasi lama tidak diport; jalur email ditangani context Notification.)
 */
final class DatabaseJobActivityNotifier implements JobActivityNotifier
{
    public const APPLY_TYPE = 'App\Notifications\Website\Candidate\ApplyJobNotification';

    public const BOOKMARK_TYPE = 'App\Notifications\Website\Candidate\BookmarkJobNotification';

    public function applied(array $candidateUser, array $job): void
    {
        $url = url('/company/my-jobs');

        $this->notify(self::APPLY_TYPE, [
            'title' => ucfirst($candidateUser['name']).' has applied your job',
            'url' => $url,
            'title2' => 'You have applied the job of '.$job['company_user_name'],
            'url2' => $url,
        ], $candidateUser, $job);
    }

    public function bookmarked(array $candidateUser, array $job): void
    {
        $url = url('/job/'.$job['slug']);

        $this->notify(self::BOOKMARK_TYPE, [
            'title' => ucfirst($candidateUser['name']).' has bookmarked your job',
            'url' => $url,
            'title2' => 'You have bookmarked a job',
            'url2' => $url,
            'job_id' => $job['id'],
        ], $candidateUser, $job);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{id:int, recent_activities_alert:bool}&array<string, mixed>  $candidateUser
     * @param  array<string, mixed>  $job
     */
    private function notify(string $type, array $payload, array $candidateUser, array $job): void
    {
        $recipients = [];

        if ($job['company_user_id'] !== null) {
            $recipients[] = $job['company_user_id'];
        }
        if ($candidateUser['recent_activities_alert']) {
            $recipients[] = $candidateUser['id'];
        }

        foreach ($recipients as $userId) {
            try {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => $type,
                    'notifiable_type' => QueryCandidateJobActivity::NOTIFIABLE_TYPE,
                    'notifiable_id' => $userId,
                    'data' => json_encode($payload),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
