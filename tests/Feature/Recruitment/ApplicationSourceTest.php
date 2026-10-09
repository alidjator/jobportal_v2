<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Matching\ApplicantMatchScores;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class ApplicationSourceTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function sourceOf(int $jobId): ?string
    {
        $source = DB::table('recruitment_applications')->where('job_id', $jobId)->where('user_id', $this->seekerId)->value('source');

        return $source === null ? null : (string) $source;
    }

    public function test_the_hint_of_the_client_is_recorded_when_it_is_one_of_the_allowed_sources(): void
    {
        foreach (['search', 'recommendation', 'alert', 'direct'] as $hint) {
            $job = $this->liveJob();
            $this->apply($job, ['source' => $hint])->assertCreated();

            $this->assertSame($hint, $this->sourceOf($job));
        }
    }

    public function test_a_missing_or_unknown_hint_counts_as_direct(): void
    {
        foreach ([[], ['source' => 'billboard'], ['source' => 'invitation'], ['source' => '']] as $body) {
            $job = $this->liveJob();
            $this->apply($job, $body)->assertCreated();

            $this->assertSame('direct', $this->sourceOf($job));
        }
    }

    public function test_an_invitation_for_the_job_wins_over_the_hint(): void
    {
        $job = $this->liveJob();
        DB::table('jobinvitation_invitations')->insert([
            'company_id' => $this->companyId, 'job_id' => $job, 'user_id' => $this->seekerId, 'invited_by_user_id' => $this->adminId,
            'status' => 'sent', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->apply($job, ['source' => 'search'])->assertCreated();

        $this->assertSame('invitation', $this->sourceOf($job));
    }

    public function test_an_invitation_to_another_job_does_not_count(): void
    {
        $other = $this->liveJob();
        DB::table('jobinvitation_invitations')->insert([
            'company_id' => $this->companyId, 'job_id' => $other, 'user_id' => $this->seekerId, 'invited_by_user_id' => $this->adminId,
            'status' => 'sent', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $job = $this->liveJob();
        $this->apply($job, ['source' => 'alert'])->assertCreated();

        $this->assertSame('alert', $this->sourceOf($job));
    }

    public function test_the_match_score_is_the_one_the_recommendations_would_give(): void
    {
        $job = $this->liveJob();
        $this->apply($job)->assertCreated();

        $stored = DB::table('recruitment_applications')->where('job_id', $job)->value('match_score');
        $expected = $this->app->make(ApplicantMatchScores::class)->percent($this->seekerId, $job);

        $this->assertSame($expected, $stored === null ? null : (int) $stored);
    }

    public function test_the_source_and_the_score_never_show_in_the_application_the_applicant_or_the_recruiter_reads(): void
    {
        $job = $this->liveJob();
        $id = (int) $this->apply($job, ['source' => 'search'])->assertCreated()->json('data.id');

        $this->assertArrayNotHasKey('source', $this->api('GET', "/me/applications/{$id}", [], $this->seekerToken)->assertOk()->json('data'));
        $this->assertArrayNotHasKey('match_score', $this->api('GET', "/company/applications/{$id}", [], $this->adminToken)->assertOk()->json('data'));
    }
}
