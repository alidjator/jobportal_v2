<?php

namespace Src\Recruitment\Infrastructure\JobManagement\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Application\JobManagement\Ports\JobApplicationRepository;
use Src\Shared\Infrastructure\Persistence\TranslatedNames;

/**
 * Tabel `applied_jobs`/`application_groups` (lamaran) dan data kandidat (`candidates`, `users`,
 * `*_translations`) dibaca read-only; satu-satunya tulisan adalah `applied_jobs.application_group_id`.
 */
class DbJobApplicationRepository implements JobApplicationRepository
{
    public function groupsWithApplications(int $companyId, int $jobId): array
    {
        $groups = DB::table('application_groups')->where('company_id', $companyId)->orderBy('id')->get();
        $applications = $groups->isEmpty() ? collect() : DB::table('applied_jobs')
            ->where('job_id', $jobId)
            ->whereIn('application_group_id', $groups->pluck('id'))
            ->orderBy('id')
            ->get();

        $candidates = $this->candidates($applications->pluck('candidate_id')->unique()->filter()->all());

        $byGroup = $applications->groupBy('application_group_id');

        return $groups->map(function ($group) use ($byGroup, $candidates) {
            $item = (array) $group;
            $item['created_at'] = TranslatableRows::iso($item['created_at']);
            $item['updated_at'] = TranslatableRows::iso($item['updated_at']);
            $item['applications'] = ($byGroup[$group->id] ?? collect())
                ->filter(fn ($app) => isset($candidates[$app->candidate_id]))
                ->map(fn ($app) => $this->application($app, $candidates[$app->candidate_id]))
                ->values()
                ->all();

            return $item;
        })->values()->all();
    }

    public function applicationBelongsToCompany(int $companyId, int $applicationId): bool
    {
        return DB::table('applied_jobs')
            ->join('jobs', 'jobs.id', '=', 'applied_jobs.job_id')
            ->where('applied_jobs.id', $applicationId)
            ->where('jobs.company_id', $companyId)
            ->exists();
    }

    public function groupBelongsToCompany(int $companyId, int $groupId): bool
    {
        return DB::table('application_groups')->where('id', $groupId)->where('company_id', $companyId)->exists();
    }

    public function moveToGroup(int $applicationId, int $groupId): void
    {
        DB::table('applied_jobs')->where('id', $applicationId)->update([
            'application_group_id' => $groupId,
            'updated_at' => now(),
        ]);
    }

    public function resumeFileForCompany(int $companyId, int $resumeId): ?string
    {
        $file = DB::table('candidate_resumes')
            ->join('applied_jobs', 'applied_jobs.candidate_resume_id', '=', 'candidate_resumes.id')
            ->join('jobs', 'jobs.id', '=', 'applied_jobs.job_id')
            ->where('candidate_resumes.id', $resumeId)
            ->where('jobs.company_id', $companyId)
            ->value('candidate_resumes.file');

        return $file === null ? null : (string) $file;
    }

    /**
     * @param  list<int>  $candidateIds
     * @return array<int, array<string, mixed>> candidate id => `api_candidate`
     */
    private function candidates(array $candidateIds): array
    {
        if ($candidateIds === []) {
            return [];
        }

        $rows = DB::table('candidates')->whereIn('id', $candidateIds)
            ->get(['id', 'user_id', 'profession_id', 'experience_id', 'education_id']);
        $users = DB::table('users')->whereIn('id', $rows->pluck('user_id'))->get(['id', 'name', 'username', 'image'])->keyBy('id');

        $professions = TranslatedNames::for('profession_translations', 'profession_id', $rows->pluck('profession_id')->filter());
        $educations = TranslatedNames::for('education_translations', 'education_id', $rows->pluck('education_id')->filter());
        $experiences = TranslatedNames::for('experience_translations', 'experience_id', $rows->pluck('experience_id')->filter());

        $result = [];
        foreach ($rows as $row) {
            $user = $users[$row->user_id] ?? null;
            $result[$row->id] = [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'profession_id' => $row->profession_id,
                'experience_id' => $row->experience_id,
                'education_id' => $row->education_id,
                'profession' => $row->profession_id ? ['id' => $row->profession_id, 'name' => $professions[$row->profession_id] ?? null] : null,
                'education' => $row->education_id ? ['id' => $row->education_id, 'name' => $educations[$row->education_id] ?? null] : null,
                'experience' => $row->experience_id ? ['id' => $row->experience_id, 'name' => $experiences[$row->experience_id] ?? null] : null,
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'image' => $user->image,
                    'image_url' => asset($user->image ?: 'backend/image/default.png'),
                ],
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function application(object $app, array $candidate): array
    {
        $item = (array) $app;
        $item['created_at'] = TranslatableRows::iso($item['created_at']);
        $item['updated_at'] = TranslatableRows::iso($item['updated_at']);
        $item['answers'] = $item['answers'] === null ? null : json_decode($item['answers'], true);
        $item['api_candidate'] = $candidate;

        return $item;
    }
}
