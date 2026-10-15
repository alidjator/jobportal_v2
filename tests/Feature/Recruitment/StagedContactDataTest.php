<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-job-discovery.md` section 4: an unverified company does not get contact data or the resume while the application is in `applied`. */
final class StagedContactDataTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function detail(): TestResponse
    {
        return $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->recruiterToken);
    }

    private function resume(): TestResponse
    {
        return $this->api('GET', "/company/applications/{$this->applicationId}/resume", token: $this->recruiterToken);
    }

    /** @return array{0: string|null, 1: string|null} the email and phone cells of the only application */
    private function exportedContact(): array
    {
        $content = $this->api('GET', "/company/jobs/{$this->job}/applications/export", token: $this->adminToken)->assertOk()->streamedContent();
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        fgetcsv($stream, escape: '');
        $row = fgetcsv($stream, escape: '');

        return [$row[3], $row[4]];
    }

    private function moveTo(string $type): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['stage_id' => $stage, 'stage_type' => $type]);
    }

    public function test_an_unverified_company_gets_no_contact_data_or_resume_while_the_application_is_applied(): void
    {
        $this->detail()->assertOk()
            ->assertJsonPath('data.applicant.contact_hidden', true)
            ->assertJsonPath('data.applicant.email', null)
            ->assertJsonPath('data.applicant.phone', null)
            ->assertJsonPath('data.applicant.whatsapp', null)
            ->assertJsonPath('data.applicant.name', 'Siti Rahma')
            ->assertJsonPath('data.applicant.experiences.0.job_title', 'Backend Developer')
            ->assertJsonPath('data.resume', ['name' => 'Siti CV.pdf']);

        $this->resume()->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:contact-not-available');
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'application.resume_downloaded')->count());
        $this->assertSame(['', ''], $this->exportedContact());
    }

    public function test_the_contact_data_opens_when_the_application_moves_to_screening(): void
    {
        $this->moveTo('screening');

        $this->detail()->assertOk()
            ->assertJsonPath('data.applicant.contact_hidden', false)
            ->assertJsonPath('data.applicant.email', 'siti@example.test')
            ->assertJsonPath('data.applicant.phone', '081234567890');
        $this->resume()->assertOk();
        $this->assertSame(['siti@example.test', '081234567890'], $this->exportedContact());
    }

    public function test_a_verified_company_sees_everything_from_the_start(): void
    {
        $this->verifyTheCompany();

        $this->detail()->assertOk()->assertJsonPath('data.applicant.contact_hidden', false)->assertJsonPath('data.applicant.email', 'siti@example.test');
        $this->resume()->assertOk();
        $this->assertSame(['siti@example.test', '081234567890'], $this->exportedContact());
    }

    public function test_only_the_applied_stage_is_closed(): void
    {
        $this->moveTo('rejected');

        $this->detail()->assertOk()->assertJsonPath('data.applicant.contact_hidden', false);
    }
}
