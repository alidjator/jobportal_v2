<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Src\Recruitment\Domain\Events\ApplicationStageChanged;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class ProcessApplicationTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    /** @var list<ApplicationStageChanged> */
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');

        Event::listen(ApplicationStageChanged::class, function (ApplicationStageChanged $event): void {
            $this->events[] = $event;
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function move(int $stageId, ?string $token = null, ?int $id = null): TestResponse
    {
        return $this->api('POST', '/company/applications/'.($id ?? $this->applicationId).'/move', ['stage_id' => $stageId], $token ?? $this->recruiterToken);
    }

    private function reject(array $body, ?string $token = null, ?int $id = null): TestResponse
    {
        return $this->api('POST', '/company/applications/'.($id ?? $this->applicationId).'/reject', $body, $token ?? $this->recruiterToken);
    }

    private function row(): object
    {
        return DB::table('recruitment_applications')->where('id', $this->applicationId)->first();
    }

    private function historyCount(): int
    {
        return DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->count();
    }

    // ----- who may process -----

    public function test_only_the_members_who_may_see_the_job_can_process_its_applications(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->postJson("/api/v1/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('interview')])->assertStatus(401);
        $this->postJson("/api/v1/company/applications/{$this->applicationId}/reject", ['reason' => 'other', 'note' => 'x'])->assertStatus(401);

        $this->move($this->stage('interview'), $this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
        $this->reject(['reason' => 'position_filled'], $this->seekerToken)->assertStatus(403);

        foreach ([$this->secondRecruiterToken, $this->otherAdminToken] as $token) {
            $this->move($this->stage('interview'), $token)->assertStatus(404);
            $this->reject(['reason' => 'position_filled'], $token)->assertStatus(404);
        }

        $this->move($this->stage('interview'), id: 999999)->assertStatus(404);
        $this->assertSame('applied', $this->row()->stage_type);
        $this->assertSame(1, $this->historyCount());
        $this->assertSame([], $this->events);

        $this->move($this->stage('interview'), $this->adminToken)->assertNoContent();
        $this->assertSame('interview', $this->row()->stage_type);
    }

    public function test_a_suspended_company_cannot_process_applications(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->move($this->stage('interview'))->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
        $this->reject(['reason' => 'position_filled'])->assertStatus(403);
        $this->assertSame('applied', $this->row()->stage_type);
    }

    // ----- moving -----

    public function test_a_move_changes_the_stage_records_the_history_and_announces_it(): void
    {
        $interview = $this->stage('interview');
        $applied = $this->stage('applied');

        $this->move($interview)->assertNoContent();

        $row = $this->row();
        $this->assertSame($interview, (int) $row->stage_id);
        $this->assertSame('interview', $row->stage_type);
        $this->assertSame($this->seekerId.'-'.$this->job, $row->active_key);

        $entry = DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->orderByDesc('id')->first();
        $this->assertSame([$applied, $interview, 'applied', 'interview', 'recruiter', $this->recruiterId, null, null], [
            (int) $entry->from_stage_id, (int) $entry->to_stage_id, $entry->from_type, $entry->to_type, $entry->actor_kind, (int) $entry->actor_id, $entry->rejection_reason, $entry->note,
        ]);

        $this->assertCount(1, $this->events);
        $event = $this->events[0];
        $this->assertSame([$this->applicationId, $this->job, $this->companyId, $this->seekerId, 'applied', 'interview'], [
            $event->applicationId, $event->jobId, $event->companyId, $event->applicantUserId, $event->fromType->value, $event->toType->value,
        ]);

        // The recruiter's detail and the applicant's own view follow.
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->recruiterToken)->assertJsonPath('data.stage.type', 'interview')->assertJsonPath('data.history.1.actor.name', 'Rina Rekruter');
        $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->seekerToken)->assertJsonPath('data.status', 'interview');
    }

    public function test_a_recruiter_moves_forward_backward_and_skipping_and_hired_can_be_moved_back(): void
    {
        foreach (['offer', 'screening', 'hired', 'applied', 'interview'] as $type) {
            $this->move($this->stage($type))->assertNoContent();
            $this->assertSame($type, $this->row()->stage_type);
        }

        $this->assertSame(6, $this->historyCount());
    }

    public function test_an_extra_stage_of_the_company_is_a_valid_target(): void
    {
        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'HR interview', 'type' => 'interview'], $this->adminToken)->assertCreated()->json('data.id');

        $this->move($this->stage('interview'))->assertNoContent();
        $this->move($extra)->assertNoContent();

        $this->assertSame($extra, (int) $this->row()->stage_id);
        $this->assertSame('interview', $this->row()->stage_type);
        $this->assertCount(2, $this->events);
    }

    public function test_a_bad_target_is_refused_and_nothing_changes(): void
    {
        $this->app->make(DefaultStageProvisioner::class)->provision($this->otherCompanyId);
        $foreign = (int) DB::table('recruitment_stages')->where('company_id', $this->otherCompanyId)->where('type', 'interview')->value('id');

        foreach ([$this->stage('rejected'), $this->stage('withdrawn'), $foreign, 999999] as $stageId) {
            $this->move($stageId)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-stage')->assertJsonStructure(['errors' => ['stage_id']]);
        }

        $this->api('POST', "/company/applications/{$this->applicationId}/move", [], $this->recruiterToken)->assertStatus(422);
        $this->move($this->stage('applied'))->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:already-in-stage');

        $this->assertSame('applied', $this->row()->stage_type);
        $this->assertSame(1, $this->historyCount());
        $this->assertSame([], $this->events);
    }

    // ----- rejecting -----

    public function test_a_rejection_moves_to_the_rejected_stage_with_the_reason_and_the_note(): void
    {
        $this->reject(['reason' => 'salary_expectation_mismatch', 'note' => '  Above our band  '])->assertNoContent();

        $row = $this->row();
        $this->assertSame($this->stage('rejected'), (int) $row->stage_id);
        $this->assertSame('rejected', $row->stage_type);

        $entry = DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->orderByDesc('id')->first();
        $this->assertSame(['applied', 'rejected', 'recruiter', $this->recruiterId, 'salary_expectation_mismatch', 'Above our band'], [$entry->from_type, $entry->to_type, $entry->actor_kind, (int) $entry->actor_id, $entry->rejection_reason, $entry->note]);
        $this->assertSame(['rejected'], array_map(fn (ApplicationStageChanged $e): string => $e->toType->value, $this->events));

        // The applicant sees the status only: neither the reason nor the note.
        $detail = $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->seekerToken)->assertOk();
        $detail->assertJsonPath('data.status', 'rejected');
        $this->assertStringNotContainsString('Above our band', (string) json_encode($detail->json()));
        $this->assertStringNotContainsString('salary_expectation_mismatch', (string) json_encode($detail->json()));
    }

    public function test_every_reason_works_and_the_note_rules_hold(): void
    {
        $reasons = ['qualifications_mismatch', 'experience_insufficient', 'salary_expectation_mismatch', 'location_mismatch', 'failed_screening', 'position_filled'];

        foreach ($reasons as $index => $reason) {
            $job = $this->insertJob('Job '.$index, 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
            $id = (int) $this->apply($job)->assertCreated()->json('data.id');

            $this->reject(['reason' => $reason], id: $id)->assertNoContent();
            $this->assertSame($reason, DB::table('recruitment_application_stage_history')->where('application_id', $id)->orderByDesc('id')->value('rejection_reason'));
            $this->assertNull(DB::table('recruitment_application_stage_history')->where('application_id', $id)->orderByDesc('id')->value('note'));
        }

        // "other" needs a note; a note is at most 1,000 characters; the reason must be on the list.
        $this->reject(['reason' => 'other'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-rejection')->assertJsonStructure(['errors' => ['note']]);
        $this->reject(['reason' => 'other', 'note' => '   '])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-rejection');
        $this->reject(['reason' => 'position_filled', 'note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-rejection');
        $this->reject(['reason' => 'because'])->assertStatus(422);
        $this->reject([])->assertStatus(422);
        $this->assertSame('applied', $this->row()->stage_type);

        $this->reject(['reason' => 'other', 'note' => 'The role was cancelled.'])->assertNoContent();
        $this->assertSame('The role was cancelled.', DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->orderByDesc('id')->value('note'));
    }

    public function test_a_rejected_applicant_cannot_apply_to_the_job_again(): void
    {
        $this->reject(['reason' => 'position_filled'])->assertNoContent();

        $this->apply($this->job)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:already-applied');
    }

    public function test_an_application_can_be_rejected_from_hired_too(): void
    {
        $this->move($this->stage('hired'))->assertNoContent();
        $this->reject(['reason' => 'position_filled'])->assertNoContent();

        $this->assertSame('rejected', $this->row()->stage_type);
        $this->assertSame('hired', DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->orderByDesc('id')->value('from_type'));
    }

    // ----- final applications -----

    public function test_a_rejected_or_withdrawn_application_is_final(): void
    {
        $this->reject(['reason' => 'position_filled'])->assertNoContent();
        $count = $this->historyCount();
        $this->events = [];

        $this->move($this->stage('interview'))->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:application-final');
        $this->reject(['reason' => 'position_filled'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:application-final');

        $other = $this->insertJob('Other job', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $withdrawn = (int) $this->apply($other)->assertCreated()->json('data.id');
        $this->api('POST', "/me/applications/{$withdrawn}/withdraw", token: $this->seekerToken)->assertNoContent();

        // The recruiter saw the application before the applicant withdrew, so the move meets the withdrawal.
        $this->move($this->stage('interview'), id: $withdrawn)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:application-final');
        $this->reject(['reason' => 'position_filled'], id: $withdrawn)->assertStatus(409);

        $this->assertSame($count, $this->historyCount());
        $this->assertSame([], $this->events);
        $this->assertSame('withdrawn', DB::table('recruitment_applications')->where('id', $withdrawn)->value('stage_type'));
    }

    public function test_the_applicant_can_still_withdraw_after_a_move_and_the_history_keeps_both(): void
    {
        $this->move($this->stage('offer'))->assertNoContent();
        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", token: $this->seekerToken)->assertNoContent();

        $this->assertSame(['applied', 'offer', 'withdrawn'], DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->orderBy('id')->pluck('to_type')->all());
        $this->assertSame(['applicant', 'recruiter', 'applicant'], DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->orderBy('id')->pluck('actor_kind')->all());
    }

    // ----- the status of the job -----

    public function test_a_taken_down_job_stays_readable_but_cannot_be_processed(): void
    {
        DB::table('job_jobs')->where('id', $this->job)->update(['status' => 'taken_down']);

        $this->move($this->stage('interview'))->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:job-taken-down');
        $this->reject(['reason' => 'position_filled'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:job-taken-down');
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->recruiterToken)->assertOk();
        $this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->recruiterToken)->assertOk();

        $this->assertSame('applied', $this->row()->stage_type);
        $this->assertSame(1, $this->historyCount());
    }

    public function test_every_other_job_status_can_be_processed(): void
    {
        foreach (['draft', 'pending_review', 'closed', 'expired', 'rejected'] as $status) {
            DB::table('job_jobs')->where('id', $this->job)->update(['status' => $status]);

            $this->move($this->stage($status === 'closed' ? 'screening' : 'interview'))->assertNoContent();
            $this->move($this->stage('applied'))->assertNoContent();
        }

        $this->assertSame('applied', $this->row()->stage_type);
    }

    // ----- an applicant whose account was anonymised -----

    public function test_an_application_of_an_anonymised_applicant_can_still_be_processed(): void
    {
        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['profile_snapshot' => null]);

        $this->move($this->stage('interview'))->assertNoContent();
        $this->reject(['reason' => 'position_filled'])->assertNoContent();

        $this->assertSame('rejected', $this->row()->stage_type);
    }
}
