<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/**
 * `docs/design/phase-8-rejections.md` section 2 and `docs/design/phase-8-candidate-experience.md` section 1: a rejection waits the undo window of the company before it takes effect (0 minutes
 * keeps the immediate rejection), the team can undo it, `recruitment:commit-rejections` applies the due ones, and the team can reject what is left of a closed job in the stages it chooses.
 */
final class RejectionWindowTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    /** @var list<int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->ids[] = (int) $this->apply($this->job)->assertCreated()->json('data.id');

        foreach (['window.one', 'window.two'] as $name) {
            $seeker = $this->seeker($name.'@example.test');
            $token = $this->tokenFor($seeker);
            $this->resumeId = $this->completeProfile($token);
            $this->ids[] = (int) $this->apply($this->job, token: $token)->assertCreated()->json('data.id');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function window(int $minutes): void
    {
        $this->app->make(SettingsStore::class)->setCompanyOverride(RecruitmentSettings::REJECTION_UNDO_MINUTES, $this->companyId, $minutes, null);
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    /** @return list<string> */
    private function stageTypes(): array
    {
        return DB::table('recruitment_applications')->whereIn('id', $this->ids)->orderBy('id')->pluck('stage_type')->all();
    }

    private function rejectOne(int $index = 0): TestResponse
    {
        return $this->api('POST', "/company/applications/{$this->ids[$index]}/reject", ['reason' => 'qualifications_mismatch'], $this->recruiterToken);
    }

    private function commitDue(int $afterMinutes): void
    {
        Carbon::setTestNow(now()->addMinutes($afterMinutes));
        Artisan::call('recruitment:commit-rejections');
    }

    public function test_without_a_window_a_rejection_takes_effect_at_once(): void
    {
        $this->rejectOne()->assertNoContent();

        $this->assertSame(['rejected', 'applied', 'applied'], $this->stageTypes());
        $this->assertSame(0, DB::table('recruitment_pending_rejections')->count());
    }

    public function test_with_a_window_the_rejection_waits_and_holds_the_application_where_it_is(): void
    {
        $this->window(10);

        $this->rejectOne()->assertStatus(202)->assertJsonPath('data.application_id', $this->ids[0])->assertJsonPath('data.batch_id', null);

        $this->assertSame(['applied', 'applied', 'applied'], $this->stageTypes());
        $this->assertSame(0, DB::table('recruitment_application_stage_history')->where('application_id', $this->ids[0])->where('rejection_reason', 'qualifications_mismatch')->count());

        // The team sees the waiting rejection in the list and in the detail, and nothing else can move the application meanwhile.
        $rows = $this->api('GET', "/company/jobs/{$this->job}/applications", [], $this->recruiterToken)->assertOk()->json('data');
        $this->assertSame($this->recruiterId, collect($rows)->firstWhere('id', $this->ids[0])['pending_rejection']['requested_by']);
        $this->assertNull(collect($rows)->firstWhere('id', $this->ids[1])['pending_rejection']);
        $this->api('GET', "/company/applications/{$this->ids[0]}", [], $this->recruiterToken)->assertOk()->assertJsonPath('data.pending_rejection.requested_by', $this->recruiterId);
        $this->api('POST', "/company/applications/{$this->ids[0]}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:rejection-pending');
        $this->rejectOne()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:rejection-pending');
    }

    public function test_the_team_undoes_a_waiting_rejection_and_the_application_is_free_again(): void
    {
        $this->window(10);
        $this->rejectOne()->assertStatus(202);

        $this->api('DELETE', "/company/applications/{$this->ids[0]}/pending-rejection", [], $this->recruiterToken)->assertNoContent();
        $this->api('DELETE', "/company/applications/{$this->ids[0]}/pending-rejection", [], $this->recruiterToken)->assertNotFound();

        $this->assertSame(0, DB::table('recruitment_pending_rejections')->count());
        $this->api('POST', "/company/applications/{$this->ids[0]}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertSuccessful();

        // Nothing is left of the undone rejection: the committing step finds nothing to do.
        $this->commitDue(11);
        $this->assertSame(['screening', 'applied', 'applied'], $this->stageTypes());
    }

    public function test_the_due_rejection_takes_effect_with_the_normal_rejection_and_cannot_be_undone_any_more(): void
    {
        $this->window(10);
        $this->rejectOne()->assertStatus(202);

        // The window has passed but the committing step has not run yet: too late to undo.
        Carbon::setTestNow(now()->addMinutes(11));
        $this->api('DELETE', "/company/applications/{$this->ids[0]}/pending-rejection", [], $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:rejection-committed');

        Artisan::call('recruitment:commit-rejections');

        $this->assertSame(['rejected', 'applied', 'applied'], $this->stageTypes());
        $this->assertSame(0, DB::table('recruitment_pending_rejections')->count());
        $this->assertSame(1, DB::table('recruitment_application_stage_history')->where('application_id', $this->ids[0])->where('rejection_reason', 'qualifications_mismatch')->count());
        $this->assertSame(1, DB::table('notifications')->where('type', 'application.rejected')->count());
    }

    public function test_a_rejection_waits_for_every_application_of_a_bulk_action_and_the_whole_batch_is_undone_together(): void
    {
        $this->window(10);

        $response = $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-reject", ['application_ids' => $this->ids, 'reason' => 'position_filled'], $this->recruiterToken)
            ->assertStatus(202)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.2.status', 'pending');
        $batch = (string) $response->json('meta.batch_id');

        $this->assertNotSame('', $batch);
        $this->assertSame(3, DB::table('recruitment_pending_rejections')->where('batch_id', $batch)->count());
        $this->assertSame(['applied', 'applied', 'applied'], $this->stageTypes());

        $this->api('DELETE', "/company/rejection-batches/{$batch}", [], $this->recruiterToken)->assertNoContent();
        $this->api('DELETE', "/company/rejection-batches/{$batch}", [], $this->recruiterToken)->assertNotFound();
        $this->assertSame(0, DB::table('recruitment_pending_rejections')->count());
    }

    public function test_a_rejection_is_dropped_when_the_application_left_its_stage_meanwhile_and_the_requester_is_told(): void
    {
        $this->window(10);
        $this->rejectOne()->assertStatus(202);

        // The job seeker withdraws during the window: that wins.
        $this->api('POST', "/me/applications/{$this->ids[0]}/withdraw", [], $this->seekerToken)->assertSuccessful();

        $this->commitDue(11);

        $this->assertSame(['withdrawn', 'applied', 'applied'], $this->stageTypes());
        $this->assertSame(0, DB::table('recruitment_pending_rejections')->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $this->recruiterId)->where('type', 'rejection.dropped')->count());
    }

    public function test_a_wrong_rejection_is_refused_at_once_and_nothing_waits(): void
    {
        $this->window(10);

        $this->api('POST', "/company/applications/{$this->ids[0]}/reject", ['reason' => 'other'], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-rejection');
        $this->api('POST', "/me/applications/{$this->ids[1]}/withdraw", [], $this->tokenFor((int) DB::table('recruitment_applications')->where('id', $this->ids[1])->value('user_id')))->assertSuccessful();
        $this->rejectOne(1)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-final');

        $this->assertSame(0, DB::table('recruitment_pending_rejections')->count());
    }

    public function test_the_rest_of_a_closed_job_is_rejected_in_the_stages_the_team_chooses(): void
    {
        $url = "/company/jobs/{$this->job}/applications/reject-remaining";
        $body = ['stage_ids' => [$this->stage('applied')], 'reason' => 'position_filled'];

        // While the job is live nothing is rejected this way.
        $this->api('POST', $url, $body, $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:job-still-open');

        $this->api('POST', "/company/jobs/{$this->job}/close", [], $this->adminToken)->assertOk();
        $this->api('POST', "/company/applications/{$this->ids[2]}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertSuccessful();

        // The hired stage is never one of them, and a stage of another company is not a choice.
        $this->api('POST', $url, ['stage_ids' => [$this->stage('hired')], 'reason' => 'position_filled'], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-stage');
        $this->api('POST', $url, ['stage_ids' => [999999], 'reason' => 'position_filled'], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-stage');
        $this->api('POST', $url, ['stage_ids' => [$this->stage('applied')], 'reason' => 'other'], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-rejection');

        $this->api('POST', $url, $body, $this->recruiterToken)->assertStatus(202)->assertJsonPath('data.count', 2);

        // The queue runs inline in the tests: the two in `applied` are rejected, the one in `screening` is left alone, and the member is told.
        $this->assertSame(['rejected', 'rejected', 'screening'], $this->stageTypes());
        $notice = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $this->recruiterId)->where('type', 'applications.reject_remaining_done')->first();
        $this->assertNotNull($notice);
        $this->assertSame(['rejected' => 2, 'skipped' => 0], array_intersect_key(json_decode((string) $notice->data, true), ['rejected' => 0, 'skipped' => 0]));
    }

    public function test_the_rest_of_a_closed_job_waits_the_undo_window_too(): void
    {
        $this->window(10);
        $this->api('POST', "/company/jobs/{$this->job}/close", [], $this->adminToken)->assertOk();

        $this->api('POST', "/company/jobs/{$this->job}/applications/reject-remaining", ['stage_ids' => [$this->stage('applied')], 'reason' => 'position_filled'], $this->recruiterToken)->assertStatus(202);

        $this->assertSame(['applied', 'applied', 'applied'], $this->stageTypes());
        $this->assertSame(3, DB::table('recruitment_pending_rejections')->whereNotNull('batch_id')->count());

        $this->commitDue(11);
        $this->assertSame(['rejected', 'rejected', 'rejected'], $this->stageTypes());
    }
}
