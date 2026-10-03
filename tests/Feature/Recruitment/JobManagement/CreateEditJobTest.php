<?php

namespace Tests\Feature\Recruitment\JobManagement;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\FeatureTestCase;

class CreateEditJobTest extends FeatureTestCase
{
    use SeedsJobManagement;

    public function test_create_requires_company_auth(): void
    {
        $this->getJson('/api/company/create/job')->assertUnauthorized();

        Sanctum::actingAs($this->makeCompanyUser('candidate'));
        $this->getJson('/api/company/create/job')->assertUnauthorized();
    }

    public function test_create_returns_form_lookups(): void
    {
        $this->actingAsCompany();
        $m = $this->seedMaster();
        $benefit = DB::table('benefits')->insertGetId([]);
        DB::table('benefit_translations')->insert(['benefit_id' => $benefit, 'name' => 'Insurance', 'locale' => 'en']);
        $tag = DB::table('tags')->insertGetId([]);
        DB::table('tag_translations')->insert(['tag_id' => $tag, 'name' => 'remote-ok', 'locale' => 'en']);

        $data = $this->getJson('/api/company/create/job')->assertOk()->json('data.data');

        $this->assertEqualsCanonicalizing(
            ['jobCategories', 'roles', 'benefits', 'tags', 'experiences', 'educations', 'job_types', 'salary_types'],
            array_keys($data),
        );
        $this->assertContains(['id' => $m['category'], 'name' => 'Engineering'], $data['jobCategories']);
        $this->assertContains(['id' => $m['role'], 'name' => 'Developer'], $data['roles']);
        $this->assertContains(['id' => $benefit, 'name' => 'Insurance'], $data['benefits']);
        $this->assertContains(['id' => $tag, 'name' => 'remote-ok'], $data['tags']);
        $experience = collect($data['experiences'])->firstWhere('id', $m['experience']);
        $this->assertSame('Senior', $experience['name']);
        $this->assertArrayHasKey('slug', $experience);
        $this->assertArrayHasKey('slug', collect($data['salary_types'])->firstWhere('id', $m['salaryType']));
    }

    public function test_edit_returns_job_with_tags_benefits_and_lookups(): void
    {
        $this->settings(['job_deadline_expiration_limit' => 45]);
        $this->actingAsCompany();
        $m = $this->seedMaster();
        $jobId = $this->insertJob($this->companyId, ['created_at' => now()->subDays(3)]);
        $slug = DB::table('jobs')->where('id', $jobId)->value('slug');
        $tag = DB::table('tags')->insertGetId(['created_at' => now()]);
        DB::table('tag_translations')->insert(['tag_id' => $tag, 'name' => 'php', 'locale' => 'en']);
        DB::table('job_tag')->insert(['job_id' => $jobId, 'tag_id' => $tag]);
        $benefit = DB::table('benefits')->insertGetId([]);
        DB::table('benefit_translations')->insert(['benefit_id' => $benefit, 'name' => 'Meals', 'locale' => 'en']);
        DB::table('job_benefit')->insert(['job_id' => $jobId, 'benefit_id' => $benefit]);

        $data = $this->getJson("/api/company/edit/{$slug}/job")->assertOk()->json('data.data');

        $this->assertSame($jobId, $data['job']['id']);
        $this->assertSame($slug, $data['job']['slug']);
        $this->assertSame($tag, $data['job']['tags'][0]['id']);
        $this->assertSame('php', $data['job']['tags'][0]['name']);
        $this->assertSame(['job_id' => $jobId, 'tag_id' => $tag], $data['job']['tags'][0]['pivot']);
        $this->assertSame('Meals', $data['job']['benefits'][0]['name']);
        $this->assertSame(3, $data['start_day']);
        $this->assertSame(48, $data['end_day']);
        $this->assertSame(['id' => $m['education'], 'name' => 'Bachelor'], collect($data['educations'])->firstWhere('id', $m['education']));
        $this->assertSame(['id' => $m['type'], 'name' => 'Full Time'], collect($data['job_types'])->firstWhere('id', $m['type']));
    }

    public function test_edit_is_scoped_to_own_company(): void
    {
        $other = $this->makeCompanyUser();
        $foreignJob = $this->insertJob($this->companyId);
        $foreignSlug = DB::table('jobs')->where('id', $foreignJob)->value('slug');

        $this->actingAsCompany();

        $this->getJson("/api/company/edit/{$foreignSlug}/job")->assertNotFound()->assertJson(['error' => 'Job not found']);
        $this->getJson('/api/company/edit/does-not-exist/job')->assertNotFound();
        $this->assertNotNull($other);
    }

    public function test_edit_requires_auth(): void
    {
        $this->getJson('/api/company/edit/x/job')->assertUnauthorized();
    }
}
