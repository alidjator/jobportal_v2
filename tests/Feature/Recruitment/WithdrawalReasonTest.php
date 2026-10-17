<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Src\Recruitment\Domain\WithdrawalReason;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-withdrawals.md` section 1: the reason of a withdrawal by the job seeker. */
final class WithdrawalReasonTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    public function test_a_withdrawal_without_a_reason_still_works(): void
    {
        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", token: $this->seekerToken)->assertNoContent();

        $this->assertNull(DB::table('recruitment_applications')->where('id', $this->applicationId)->value('withdrawal_reason'));
        $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->seekerToken)
            ->assertJsonPath('data.withdrawal.reason', null)
            ->assertJsonPath('data.withdrawal.by', 'applicant');
    }

    public function test_the_reason_is_stored_on_the_application_and_the_history_and_shown_to_both_sides(): void
    {
        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", ['reason' => 'salary', 'note' => 'Too low'], $this->seekerToken)->assertNoContent();

        $this->assertSame('salary', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('withdrawal_reason'));
        $this->assertSame('salary', DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->where('to_type', 'withdrawn')->value('withdrawal_reason'));

        $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->seekerToken)
            ->assertJsonPath('data.withdrawal.reason', 'salary')
            ->assertJsonPath('data.withdrawn_note', 'Too low');

        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->recruiterToken)
            ->assertJsonPath('data.withdrawal.reason', 'salary')
            ->assertJsonPath('data.withdrawal.by', 'applicant')
            ->assertJsonPath('data.withdrawal.note', 'Too low');
    }

    public function test_the_applicant_cannot_pick_an_unknown_or_team_only_reason(): void
    {
        foreach (['unreachable', 'because'] as $reason) {
            $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", ['reason' => $reason], $this->seekerToken)
                ->assertStatus(422)
                ->assertJsonPath('type', 'urn:onesis:error:invalid-withdrawal')
                ->assertJsonStructure(['errors' => ['reason']]);
        }

        // A value that is not a string never reaches the use case.
        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", ['reason' => 123], $this->seekerToken)
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:validation-failed');

        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'));
    }

    public function test_every_reason_the_applicant_may_pick_is_accepted(): void
    {
        $allowed = array_filter(WithdrawalReason::cases(), static fn (WithdrawalReason $reason): bool => ! $reason->isTeamOnly());

        $this->assertCount(7, $allowed);
        $this->assertTrue(WithdrawalReason::Unreachable->isTeamOnly());
        $this->assertTrue(WithdrawalReason::Other->requiresNoteFromTeam());
    }
}
