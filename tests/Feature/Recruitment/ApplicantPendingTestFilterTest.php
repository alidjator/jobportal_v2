<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Assessment\AssessmentInvitations;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-applicant-search.md` section 3: `filter[state]=test_pending` asks the Assessment port for the applications whose test is not finished. */
final class ApplicantPendingTestFilterTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');

        $testId = $this->newTest('company', ['title' => 'Logic and numbers']);
        $this->sectionWithQuestion('company', $testId);
        $this->company('POST', "tests/{$testId}/publish")->assertOk();

        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'assessment' => ['test_id' => $testId, 'due_at' => now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z')]], $this->recruiterToken)->assertOk();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @return list<int> */
    private function ids(string $uri): array
    {
        return array_map('intval', array_column($this->api('GET', $uri, token: $this->adminToken)->assertOk()->json('data'), 'id'));
    }

    public function test_the_filter_lists_the_applications_with_an_unfinished_test_on_both_lists(): void
    {
        $this->assertSame([$this->applicationId], $this->ids('/company/applications?filter[state]=test_pending'));
        $this->assertSame([$this->applicationId], $this->ids("/company/jobs/{$this->job}/applications?filter[state]=test_pending"));

        $this->assertSame([$this->applicationId], $this->app->make(AssessmentInvitations::class)->applicationIdsWithPendingTest($this->companyId, null));
        $this->assertSame([$this->applicationId], $this->app->make(AssessmentInvitations::class)->applicationIdsWithPendingTest($this->companyId, [$this->job]));
        $this->assertSame([], $this->app->make(AssessmentInvitations::class)->applicationIdsWithPendingTest($this->companyId, []));
        $this->assertSame([], $this->app->make(AssessmentInvitations::class)->applicationIdsWithPendingTest($this->otherCompanyId, null));
    }

    public function test_a_finished_or_cancelled_test_is_not_pending(): void
    {
        DB::table('assessment_invitations')->where('application_id', $this->applicationId)->update(['status' => 'submitted']);
        $this->assertSame([], $this->ids('/company/applications?filter[state]=test_pending'));

        DB::table('assessment_invitations')->where('application_id', $this->applicationId)->update(['status' => 'started']);
        $this->assertSame([$this->applicationId], $this->ids('/company/applications?filter[state]=test_pending'));

        DB::table('assessment_invitations')->where('application_id', $this->applicationId)->update(['status' => 'cancelled']);
        $this->assertSame([], $this->ids('/company/applications?filter[state]=test_pending'));
    }
}
