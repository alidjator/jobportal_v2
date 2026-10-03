<?php

namespace Src\Recruitment\Infrastructure\JobBoard\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Domain\JobBoard\CandidateJobActivity;
use Src\Recruitment\Domain\JobBoard\Page;

/**
 * Tabel milik Recruitment: `applied_jobs`, `bookmark_candidate_job`.
 * Read-only dari context lain: `candidates` (CandidateProfile, id kandidat dari user), `candidate_resumes`
 * (kepemilikan resume), `users` (info notifikasi), `application_groups` (Company, grup default lamaran),
 * `notifications` (Notification, job alert).
 */
final class QueryCandidateJobActivity implements CandidateJobActivity
{
    /** Morph class `User` aplikasi lama, dipakai pada baris `notifications`. */
    public const NOTIFIABLE_TYPE = 'App\Models\User';

    public const RELATED_JOB_NOTIFICATION = 'App\Notifications\Website\Candidate\RelatedJobNotification';

    public function __construct(private readonly JobRows $rows) {}

    public function candidateIdForUser(int $userId): ?int
    {
        $id = DB::table('candidates')->where('user_id', $userId)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function userInfo(int $userId): ?array
    {
        $user = DB::table('users')->where('id', $userId)->first(['id', 'name', 'role', 'recent_activities_alert']);

        return $user ? [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'role' => (string) $user->role,
            'recent_activities_alert' => (bool) $user->recent_activities_alert,
        ] : null;
    }

    public function appliedJobs(int $candidateId, int $perPage, int $page): Page
    {
        $query = $this->rows->base()
            ->join('applied_jobs', 'applied_jobs.job_id', '=', 'jobs.id')
            ->where('applied_jobs.candidate_id', $candidateId);

        $total = (clone $query)->count();

        $items = $this->rows->select($query, $candidateId)
            ->addSelect('applied_jobs.created_at as applied_at')
            ->orderByDesc('applied_jobs.id')
            ->forPage($page, $perPage)->get();

        return new Page($this->rows->hydrate($items), $total, $perPage, $page);
    }

    public function bookmarkedJobs(int $candidateId, int $perPage, int $page): Page
    {
        $query = $this->rows->base()
            ->join('bookmark_candidate_job', 'bookmark_candidate_job.job_id', '=', 'jobs.id')
            ->where('bookmark_candidate_job.candidate_id', $candidateId);

        $total = (clone $query)->count();

        $items = $this->rows->select($query, $candidateId)
            ->orderByDesc('bookmark_candidate_job.id')
            ->forPage($page, $perPage)->get();

        return new Page($this->rows->hydrate($items), $total, $perPage, $page);
    }

    public function toggleBookmark(int $candidateId, int $jobId): bool
    {
        return DB::transaction(function () use ($candidateId, $jobId): bool {
            $existing = DB::table('bookmark_candidate_job')
                ->where('candidate_id', $candidateId)->where('job_id', $jobId);

            if ((clone $existing)->lockForUpdate()->exists()) {
                $existing->delete();

                return false;
            }

            DB::table('bookmark_candidate_job')->insert([
                'candidate_id' => $candidateId,
                'job_id' => $jobId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    public function jobSummary(int $jobId): ?array
    {
        $job = DB::table('jobs')
            ->leftJoin('companies', 'companies.id', '=', 'jobs.company_id')
            ->leftJoin('users', 'users.id', '=', 'companies.user_id')
            ->where('jobs.id', $jobId)
            ->first(['jobs.id', 'jobs.title', 'jobs.slug', 'jobs.apply_on', 'jobs.company_id', 'users.id as company_user_id', 'users.name as company_user_name']);

        return $job ? [
            'id' => (int) $job->id,
            'title' => (string) $job->title,
            'slug' => (string) $job->slug,
            'apply_on' => (string) $job->apply_on,
            'company_id' => $job->company_id === null ? null : (int) $job->company_id,
            'company_user_id' => $job->company_user_id === null ? null : (int) $job->company_user_id,
            'company_user_name' => $job->company_user_name,
        ] : null;
    }

    public function ownsResume(int $candidateId, string $resumeId): bool
    {
        return DB::table('candidate_resumes')->where('id', $resumeId)->where('candidate_id', $candidateId)->exists();
    }

    public function hasApplied(int $candidateId, int $jobId): bool
    {
        return DB::table('applied_jobs')->where('candidate_id', $candidateId)->where('job_id', $jobId)->exists();
    }

    public function apply(int $candidateId, int $jobId, string $resumeId, string $coverLetter): void
    {
        $companyId = DB::table('jobs')->where('id', $jobId)->value('company_id');

        $groups = DB::table('application_groups')->where('company_id', $companyId)->orderBy('id')->get(['id', 'is_deleteable']);
        $groupId = null;
        foreach ($groups as $group) {
            $groupId ??= $group->id;
            if (! $group->is_deleteable) {
                $groupId = $group->id;
                break;
            }
        }

        DB::table('applied_jobs')->insert([
            'candidate_id' => $candidateId,
            'job_id' => $jobId,
            'cover_letter' => $coverLetter,
            'candidate_resume_id' => $resumeId,
            'application_group_id' => $groupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function jobAlerts(int $userId, int $perPage, int $page): Page
    {
        $query = DB::table('notifications')
            ->where('notifiable_type', self::NOTIFIABLE_TYPE)
            ->where('notifiable_id', $userId)
            ->where('type', self::RELATED_JOB_NOTIFICATION);

        $total = (clone $query)->count();
        $notifications = $query->orderByDesc('created_at')->forPage($page, $perPage)->get(['data']);

        $jobIds = $notifications->map(fn (object $n) => json_decode($n->data, true)['job_id'] ?? null)->filter()->unique()->values();

        $jobs = [];
        if ($jobIds->isNotEmpty()) {
            $found = $this->rows->select($this->rows->base()->whereIn('jobs.id', $jobIds), null)->get();
            foreach ($this->rows->hydrate($found) as $job) {
                $jobs[$job['id']] = $job;
            }
        }

        $items = $notifications->map(function (object $n) use ($jobs): array {
            $jobId = json_decode($n->data, true)['job_id'] ?? null;

            return ['job' => $jobId !== null ? ($jobs[(int) $jobId] ?? null) : null];
        })->all();

        return new Page($items, $total, $perPage, $page);
    }
}
