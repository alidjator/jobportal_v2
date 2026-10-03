<?php

namespace Tests\Feature\Recruitment\JobManagement;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Tests\FeatureTestCase;

class ListJobsAndApplicationsTest extends FeatureTestCase
{
    use SeedsJobManagement;

    public function test_all_endpoints_require_company_auth(): void
    {
        $this->getJson('/api/company/job')->assertUnauthorized();
        $this->getJson('/api/company/job/1/applications')->assertUnauthorized();
        $this->postJson('/api/company/job/applications/1/group-update', [])->assertUnauthorized();
        $this->getJson('/api/company/job/download-cv/1')->assertUnauthorized();

        Sanctum::actingAs($this->makeCompanyUser('candidate'));
        $this->getJson('/api/company/job')->assertUnauthorized();
    }

    // ---- GET /company/job ----

    public function test_lists_active_jobs_of_own_company_five_per_page(): void
    {
        $this->actingAsCompany();
        $mine = $this->companyId;
        $type = DB::table('job_types')->insertGetId(['slug' => 't'.random_int(1, 9999999)]);
        DB::table('job_type_translations')->insert(['job_type_id' => $type, 'name' => 'Contract', 'locale' => 'en']);
        $ids = [];
        foreach (range(1, 7) as $i) {
            $ids[] = $this->insertJob($mine, ['created_at' => now()->subMinutes(10 - $i), 'job_type_id' => $type]);
        }
        $expired = $this->insertJob($mine, ['status' => 'expired']);
        $this->makeCompanyUser();
        $this->insertJob($this->companyId);   // perusahaan lain
        $this->actingAsCompanyById($mine);

        $page1 = $this->getJson('/api/company/job')->assertOk()->json('data');

        $this->assertSame(1, $page1['current_page']);
        $this->assertSame(5, $page1['per_page']);
        $this->assertSame(7, $page1['total']);
        $this->assertSame(array_reverse(array_slice($ids, 2)), array_column($page1['data'], 'id'));
        $first = $page1['data'][0];
        $this->assertSame(['id', 'title', 'company_id', 'country', 'max_salary', 'min_salary', 'job_type_id', 'slug', 'deadline', 'status'], array_slice(array_keys($first), 0, 10));
        $this->assertSame(['id' => $type, 'name' => 'Contract'], $first['job_type']);
        $this->assertSame(['id' => $mine, 'user' => null], $first['company']);
        $this->assertSame(0, $first['applied_jobs_count']);
        $this->assertArrayHasKey('days_remaining', $first);

        $page2 = $this->getJson('/api/company/job?page=2')->assertOk()->json('data');
        $this->assertCount(2, $page2['data']);

        $this->getJson('/api/company/job?status=expired')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $expired);
        $this->getJson('/api/company/job?status=pending')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_applied_jobs_count(): void
    {
        $this->actingAsCompany();
        $job = $this->insertJob($this->companyId);
        $this->makeApplication($job);
        $this->makeApplication($job);

        $this->getJson('/api/company/job')->assertOk()->assertJsonPath('data.data.0.applied_jobs_count', 2);
    }

    // ---- applications ----

    public function test_lists_applications_grouped_by_application_group(): void
    {
        $this->actingAsCompany();
        $job = $this->insertJob($this->companyId);
        $otherJob = $this->insertJob($this->companyId);
        $groupA = $this->makeGroup($this->companyId, 'Screening');
        $groupB = $this->makeGroup($this->companyId, 'Interview');
        $app = $this->makeApplication($job, $groupA, ['cover_letter' => 'Hello', 'answers' => json_encode([['q' => 'x']])]);
        $this->makeApplication($otherJob, $groupA);
        $this->makeApplication($job, null);

        $data = $this->getJson("/api/company/job/{$job}/applications")->assertOk()->json('data');

        $this->assertSame([$groupA, $groupB], array_column($data, 'id'));
        $this->assertSame('Screening', $data[0]['name']);
        $this->assertCount(1, $data[0]['applications']);
        $this->assertSame([], $data[1]['applications']);
        $application = $data[0]['applications'][0];
        $this->assertSame($app, $application['id']);
        $this->assertSame('Hello', $application['cover_letter']);
        $this->assertSame([['q' => 'x']], $application['answers']);
        $this->assertSame($job, $application['job_id']);
        $candidate = $application['api_candidate'];
        $this->assertSame(['id', 'user_id', 'profession_id', 'experience_id', 'education_id', 'profession', 'education', 'experience', 'user'], array_keys($candidate));
        $this->assertSame('Bachelor', $candidate['education']['name']);
        $this->assertSame('Senior', $candidate['experience']['name']);
        $this->assertSame('Cook', $candidate['profession']['name']);
        $this->assertSame(['id', 'name', 'username', 'image', 'image_url'], array_keys($candidate['user']));
        $this->assertStringEndsWith('backend/image/default.png', $candidate['user']['image_url']);
    }

    public function test_applications_of_foreign_job_is_404(): void
    {
        $this->makeCompanyUser();
        $foreign = $this->insertJob($this->companyId);
        $this->actingAsCompany();

        $this->getJson("/api/company/job/{$foreign}/applications")->assertNotFound();
        $this->getJson('/api/company/job/999999999/applications')->assertNotFound();
    }

    // ---- group update ----

    public function test_moves_application_to_another_group(): void
    {
        $this->actingAsCompany();
        $job = $this->insertJob($this->companyId);
        $from = $this->makeGroup($this->companyId, 'A');
        $to = $this->makeGroup($this->companyId, 'B');
        $app = $this->makeApplication($job, $from);

        $this->postJson("/api/company/job/applications/{$app}/group-update", ['group' => $to])
            ->assertOk()->assertExactJson(['data' => ['message' => 'Application updated successful!']]);

        $this->assertSame($to, (int) DB::table('applied_jobs')->where('id', $app)->value('application_group_id'));
    }

    public function test_group_update_validation_and_ownership(): void
    {
        $this->actingAsCompany();
        $mine = $this->companyId;
        $job = $this->insertJob($mine);
        $myGroup = $this->makeGroup($mine, 'Mine');
        $app = $this->makeApplication($job, $myGroup);

        $this->makeCompanyUser();
        $foreignJob = $this->insertJob($this->companyId);
        $foreignGroup = $this->makeGroup($this->companyId, 'Foreign');
        $foreignApp = $this->makeApplication($foreignJob, $foreignGroup);
        $this->actingAsCompanyById($mine);

        $this->postJson("/api/company/job/applications/{$app}/group-update", [])->assertStatus(422)->assertJsonValidationErrors(['group']);
        $this->postJson("/api/company/job/applications/{$app}/group-update", ['group' => 'x'])->assertStatus(422);
        // lamaran milik perusahaan lain
        $this->postJson("/api/company/job/applications/{$foreignApp}/group-update", ['group' => $myGroup])->assertNotFound();
        $this->assertSame($foreignGroup, (int) DB::table('applied_jobs')->where('id', $foreignApp)->value('application_group_id'));
        // grup milik perusahaan lain
        $this->postJson("/api/company/job/applications/{$app}/group-update", ['group' => $foreignGroup])->assertNotFound();
        $this->assertSame($myGroup, (int) DB::table('applied_jobs')->where('id', $app)->value('application_group_id'));
        // lamaran tidak ada
        $this->postJson('/api/company/job/applications/999999999/group-update', ['group' => $myGroup])->assertNotFound();
    }

    // ---- download cv ----

    public function test_download_cv_returns_file_url_string(): void
    {
        $this->actingAsCompany();
        $job = $this->insertJob($this->companyId);
        $app = $this->makeApplication($job, null, [], 'uploads/resume/cv-test.pdf');
        $resume = (int) DB::table('applied_jobs')->where('id', $app)->value('candidate_resume_id');

        $response = $this->get("/api/company/job/download-cv/{$resume}", ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(asset('uploads/resume/cv-test.pdf'), $response->getContent());
    }

    public function test_download_cv_only_for_resumes_used_on_own_jobs(): void
    {
        $this->actingAsCompany();
        $mine = $this->companyId;
        $this->makeCompanyUser();
        $foreignJob = $this->insertJob($this->companyId);
        $app = $this->makeApplication($foreignJob, null, [], 'uploads/resume/secret.pdf');
        $foreignResume = (int) DB::table('applied_jobs')->where('id', $app)->value('candidate_resume_id');
        $this->actingAsCompanyById($mine);

        $this->getJson("/api/company/job/download-cv/{$foreignResume}")->assertNotFound();
        $this->getJson('/api/company/job/download-cv/999999999')->assertNotFound();
    }

    // ---- helpers ----

    private function actingAsCompanyById(int $companyId): void
    {
        $userId = DB::table('companies')->where('id', $companyId)->value('user_id');
        Sanctum::actingAs(UserModel::query()->findOrFail($userId));
        $this->companyId = $companyId;
    }

    private function makeGroup(int $companyId, string $name): int
    {
        return DB::table('application_groups')->insertGetId([
            'company_id' => $companyId, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeApplication(int $jobId, ?int $groupId = null, array $override = [], string $resumeFile = 'uploads/resume/a.pdf'): int
    {
        $profession = DB::table('professions')->insertGetId(['created_at' => now()]);
        DB::table('profession_translations')->insert(['profession_id' => $profession, 'name' => 'Cook', 'locale' => 'en']);
        $education = DB::table('education')->insertGetId(['slug' => 'ed'.random_int(1, 99999999)]);
        DB::table('education_translations')->insert(['education_id' => $education, 'name' => 'Bachelor', 'locale' => 'en']);
        $experience = DB::table('experiences')->insertGetId(['slug' => 'ex'.random_int(1, 99999999)]);
        DB::table('experience_translations')->insert(['experience_id' => $experience, 'name' => 'Senior', 'locale' => 'en']);

        $userId = DB::table('users')->insertGetId([
            'name' => 'Cand', 'username' => 'cand'.random_int(1, 999999999), 'email' => 'c'.random_int(1, 999999999).'@example.test',
            'role' => 'candidate', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $candidate = DB::table('candidates')->insertGetId([
            'user_id' => $userId, 'profession_id' => $profession, 'experience_id' => $experience, 'education_id' => $education,
            'profile_mark' => 0, 'education_mark' => 0, 'experience_mark' => 0, 'resume_mark' => 0,
        ]);
        $resume = DB::table('candidate_resumes')->insertGetId(['candidate_id' => $candidate, 'name' => 'CV', 'file' => $resumeFile]);

        return DB::table('applied_jobs')->insertGetId(array_merge([
            'candidate_id' => $candidate, 'job_id' => $jobId, 'candidate_resume_id' => $resume,
            'application_group_id' => $groupId, 'created_at' => now(), 'updated_at' => now(),
        ], $override));
    }
}
