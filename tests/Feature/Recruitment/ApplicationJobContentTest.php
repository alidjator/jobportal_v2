<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Src\Recruitment\Application\EraseApplicantData;
use Src\Recruitment\Application\Export\ApplicationsDataExporter;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-job-copy-in-application.md`: the content of the job as the candidate read it when they applied, kept with the application. */
final class ApplicationJobContentTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $php;

    private int $insurance;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->buildApplicationFixtures();

        $this->php = $this->skillId('php');
        $this->insurance = $this->benefitId('insurance');
        DB::table('reference_skills')->where('id', $this->php)->update(['name' => json_encode(['id' => 'PHP (id)', 'en' => 'PHP'])]);
        DB::table('reference_benefits')->where('id', $this->insurance)->update(['name' => json_encode(['id' => 'Asuransi', 'en' => 'Insurance'])]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attributes */
    private function richJob(array $attributes = []): int
    {
        $job = $this->insertJob('Backend Developer', 'active', attributes: [
            'deadline' => '2026-12-31', 'description' => 'Build the APIs of the portal.', 'work_mode' => 'hybrid', 'salary_min' => 8_000_000, 'salary_max' => 12_000_000,
            'salary_period_id' => $this->referenceId('reference_salary_periods', 'monthly'), 'salary_visible' => true, 'address_line' => 'Jl. Merdeka 1', ...$this->locationChain(), ...$attributes,
        ]);
        DB::table('job_job_skills')->insert(['job_id' => $job, 'skill_id' => $this->php, 'created_at' => now()]);
        DB::table('job_job_benefits')->insert(['job_id' => $job, 'benefit_id' => $this->insurance, 'created_at' => now()]);

        return $job;
    }

    private function applicationId(int $job): int
    {
        return (int) $this->apply($job)->assertCreated()->json('data.id');
    }

    public function test_applying_keeps_the_content_of_the_job_and_the_candidate_reads_it_in_their_language(): void
    {
        $job = $this->richJob();
        $id = $this->applicationId($job);

        $stored = json_decode((string) DB::table('recruitment_applications')->where('id', $id)->value('job_snapshot'), true);
        $this->assertSame([1, 'Backend Developer', 'Build the APIs of the portal.', 'hybrid', false], [$stored['version'], $stored['title'], $stored['description'], $stored['work_mode'], $stored['as_of_migration']]);
        $this->assertEquals(['id' => 'PHP (id)', 'en' => 'PHP'], $stored['skills'][0]['name']);

        $english = $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken, headers: ['Accept-Language' => 'en'])->assertOk();
        $english->assertJsonPath('data.job_content.title', 'Backend Developer')
            ->assertJsonPath('data.job_content.skills.0.name', 'PHP')
            ->assertJsonPath('data.job_content.benefits.0.name', 'Insurance')
            ->assertJsonPath('data.job_content.salary.min', 8000000)
            ->assertJsonPath('data.job_content.salary.period.name', 'monthly')
            ->assertJsonPath('data.job_content.locations.0.address_line', 'Jl. Merdeka 1')
            ->assertJsonPath('data.job_content.as_of_migration', false);

        $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken, headers: ['Accept-Language' => 'id'])->assertOk()
            ->assertJsonPath('data.job_content.skills.0.name', 'PHP (id)')
            ->assertJsonPath('data.job_content.benefits.0.name', 'Asuransi');
    }

    public function test_the_copy_does_not_change_when_the_job_does(): void
    {
        $job = $this->richJob();
        $id = $this->applicationId($job);

        DB::table('job_jobs')->where('id', $job)->update(['title' => 'Senior Backend Developer', 'description' => 'A completely different text.', 'status' => 'closed']);

        $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken)->assertOk()
            ->assertJsonPath('data.job_content.title', 'Backend Developer')
            ->assertJsonPath('data.job_content.description', 'Build the APIs of the portal.');
    }

    public function test_a_salary_the_job_hides_is_not_copied(): void
    {
        $id = $this->applicationId($this->richJob(['salary_visible' => false]));

        $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken)->assertOk()->assertJsonPath('data.job_content.salary', null);
        $this->assertNull(json_decode((string) DB::table('recruitment_applications')->where('id', $id)->value('job_snapshot'), true)['salary']);
    }

    public function test_the_copy_holds_nothing_about_the_company(): void
    {
        $id = $this->applicationId($this->richJob());

        $stored = json_decode((string) DB::table('recruitment_applications')->where('id', $id)->value('job_snapshot'), true);

        $this->assertEqualsCanonicalizing(['version', 'title', 'description', 'work_mode', 'locations', 'salary', 'skills', 'benefits', 'tags', 'as_of_migration'], array_keys($stored));
    }

    public function test_the_list_does_not_carry_the_copy_and_another_applicant_cannot_read_it(): void
    {
        $id = $this->applicationId($this->richJob());

        $list = $this->api('GET', '/me/applications', token: $this->seekerToken)->assertOk();
        $this->assertArrayNotHasKey('job_content', $list->json('data.0'));

        $other = $this->tokenFor($this->seeker('budi.s@example.test', name: 'Budi Santoso'));
        $this->api('GET', "/me/applications/{$id}", token: $other)->assertNotFound();
    }

    public function test_the_backfill_fills_older_applications_once_and_marks_them(): void
    {
        $job = $this->richJob();
        $id = $this->applicationId($job);
        DB::table('recruitment_applications')->where('id', $id)->update(['job_snapshot' => null]);
        DB::table('job_jobs')->where('id', $job)->update(['title' => 'Backend Developer II']);

        $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken)->assertOk()->assertJsonPath('data.job_content', null);

        Artisan::call('recruitment:backfill-job-snapshots');

        $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken)->assertOk()
            ->assertJsonPath('data.job_content.title', 'Backend Developer II')
            ->assertJsonPath('data.job_content.as_of_migration', true);

        DB::table('job_jobs')->where('id', $job)->update(['title' => 'Backend Developer III']);
        Artisan::call('recruitment:backfill-job-snapshots');

        $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken)->assertOk()->assertJsonPath('data.job_content.title', 'Backend Developer II');
    }

    public function test_the_backfill_leaves_an_erased_application_alone(): void
    {
        $id = $this->applicationId($this->richJob());
        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);

        $this->assertNull(DB::table('recruitment_applications')->where('id', $id)->value('job_snapshot'));

        Artisan::call('recruitment:backfill-job-snapshots');

        $this->assertNull(DB::table('recruitment_applications')->where('id', $id)->value('job_snapshot'));
    }

    public function test_the_export_holds_the_content_the_candidate_applied_for(): void
    {
        $this->applicationId($this->richJob());

        $data = $this->app->make(ApplicationsDataExporter::class)->export($this->seekerId)->data;

        $this->assertStringContainsString('Build the APIs of the portal.', (string) json_encode($data));
        $this->assertStringContainsString('job_content', (string) json_encode($data));
    }
}
