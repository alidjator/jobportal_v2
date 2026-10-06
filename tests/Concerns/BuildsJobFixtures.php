<?php

namespace Tests\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;

/**
 * A company with an admin and two recruiters, another company with its own admin, and helpers to call the job API and to insert jobs directly.
 * Time is fixed at 2026-10-05 09:00 UTC; the token of each member lasts a day.
 */
trait BuildsJobFixtures
{
    use CreatesReferenceRows;

    protected int $companyId;

    protected int $otherCompanyId;

    protected int $adminId;

    protected int $recruiterId;

    protected int $secondRecruiterId;

    protected int $otherAdminId;

    protected string $adminToken;

    protected string $recruiterToken;

    protected string $secondRecruiterToken;

    protected string $otherAdminToken;

    protected function buildJobFixtures(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');

        $this->companyId = $this->companyRowId('pt-maju');
        $this->otherCompanyId = $this->companyRowId('pt-lain');
        $this->adminId = $this->jobMember($this->companyId, 'Andi Admin', 'andi@example.test', 'company_admin');
        $this->recruiterId = $this->jobMember($this->companyId, 'Rina Rekruter', 'rina@example.test', 'recruiter');
        $this->secondRecruiterId = $this->jobMember($this->companyId, 'Sari Rekruter', 'sari@example.test', 'recruiter');
        $this->otherAdminId = $this->jobMember($this->otherCompanyId, 'Budi Admin', 'budi@example.test', 'company_admin');

        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->secondRecruiterToken = $this->tokenFor($this->secondRecruiterId);
        $this->otherAdminToken = $this->tokenFor($this->otherAdminId);
    }

    protected function tearDownJobFixtures(): void
    {
        Carbon::setTestNow();
    }

    protected function jobMember(int $companyId, string $name, string $email, string $role): int
    {
        $user = UserModel::query()->create(['type' => 'company_member', 'name' => $name, 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00']);
        DB::table('company_members')->insert(['company_id' => $companyId, 'user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return (int) $user->id;
    }

    protected function tokenFor(int $userId): string
    {
        return UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    protected function api(string $method, string $uri, array $body = [], ?string $token = null, array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->json($method, '/api/v1'.$uri, $body, $headers);
    }

    /**
     * Inserts a job straight into the tables.
     *
     * @param  list<int>  $assignees
     * @param  array<string, mixed>  $attributes
     */
    protected function insertJob(string $title = 'Backend Developer', string $status = 'draft', ?array $assignees = null, ?int $companyId = null, array $attributes = []): int
    {
        $id = (int) DB::table('job_jobs')->insertGetId([
            'company_id' => $companyId ?? $this->companyId, 'title' => $title, 'slug' => strtolower(str_replace(' ', '-', $title)), 'status' => $status,
            'created_by_user_id' => $this->adminId, 'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);

        foreach ($assignees ?? [$this->adminId] as $userId) {
            DB::table('job_assignments')->insert(['job_id' => $id, 'user_id' => $userId, 'created_at' => now()]);
        }

        return $id;
    }

    protected function jobRow(int $id): ?object
    {
        return DB::table('job_jobs')->find($id);
    }

    /** @return list<int> */
    protected function assigneesOf(int $jobId): array
    {
        return array_map('intval', DB::table('job_assignments')->where('job_id', $jobId)->orderBy('id')->pluck('user_id')->all());
    }

    /**
     * A body that sets every field of a job to valid values.
     *
     * @return array<string, mixed>
     */
    protected function fullJobBody(string $title = 'Senior Backend Developer'): array
    {
        return [
            'title' => $title,
            'description' => trim(str_repeat('We build the portal. ', 5)),
            'category_id' => $this->referenceId('reference_job_categories', 'engineering'),
            'role_id' => $this->referenceId('reference_job_roles', 'backend-developer'),
            'job_type_id' => $this->referenceId('reference_job_types', 'full-time'),
            'job_level_id' => $this->referenceId('reference_job_levels', 'senior'),
            'work_time_id' => $this->referenceId('reference_work_times', 'day-shift'),
            'experience_level_id' => $this->referenceId('reference_experience_levels', 'five-years'),
            'education_level_id' => $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]),
            'vacancies' => 3,
            'deadline' => '2026-12-31',
            'salary_min' => 10000000,
            'salary_max' => 15000000,
            'salary_period_id' => $this->referenceId('reference_salary_periods', 'monthly'),
            'salary_visible' => false,
            'work_mode' => 'hybrid',
            ...$this->locationChain(),
            'address_line' => 'Jl. Setiabudi 1',
            'skill_ids' => [$this->skillId('php'), $this->skillId('laravel')],
            'tags' => ['PHP', 'Laravel ', 'c++'],
            'benefit_ids' => [$this->benefitId('insurance')],
        ];
    }
}
