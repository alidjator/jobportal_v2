<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class BulkActionsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    /** @var list<int> three applications of the job, oldest first */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-08 03:00:00');
        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->ids[] = (int) $this->apply($this->job)->assertCreated()->json('data.id');

        foreach (['bulk.one@example.test', 'bulk.two@example.test'] as $email) {
            $token = $this->tokenFor($this->seeker($email));
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

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function stageTypes(): array
    {
        return DB::table('recruitment_applications')->whereIn('id', $this->ids)->orderBy('id')->pluck('stage_type')->all();
    }

    /** @param  array<string, mixed>  $body */
    private function bulkMove(array $body, ?string $token = null, ?int $job = null): TestResponse
    {
        return $this->api('POST', '/company/jobs/'.($job ?? $this->job).'/applications/bulk-move', $body, $token ?? $this->recruiterToken);
    }

    /** @param  array<string, mixed>  $overrides */
    private function baseSchedule(array $overrides = []): array
    {
        // Monday 2026-10-12, 09:00 Jakarta.
        return $this->interviewSchedule(['starts_at' => '2026-10-12T02:00:00Z', 'duration_minutes' => 60, 'gap_minutes' => 0, ...$overrides]);
    }

    private function slots(): array
    {
        $zone = new \DateTimeZone('Asia/Jakarta');

        return DB::table('recruitment_interviews')->whereIn('application_id', $this->ids)->orderBy('application_id')->get()
            ->map(fn (object $row): string => (new \DateTimeImmutable($row->starts_at.' UTC'))->setTimezone($zone)->format('m-d H:i'))->all();
    }

    // ----- bulk reject -----

    public function test_a_bulk_rejection_rejects_every_application_with_the_same_reason_and_reports_each(): void
    {
        $this->api('POST', "/me/applications/{$this->ids[2]}/withdraw", [], $this->tokenFor((int) DB::table('recruitment_applications')->where('id', $this->ids[2])->value('user_id')))->assertSuccessful();

        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-reject", ['application_ids' => $this->ids, 'reason' => 'position_filled'], $this->recruiterToken)
            ->assertOk()
            ->assertExactJson(['data' => [
                ['application_id' => $this->ids[0], 'status' => 'moved', 'code' => null],
                ['application_id' => $this->ids[1], 'status' => 'moved', 'code' => null],
                ['application_id' => $this->ids[2], 'status' => 'skipped', 'code' => 'application-final'],
            ]]);

        $this->assertSame(['rejected', 'rejected', 'withdrawn'], $this->stageTypes());
        $this->assertSame(2, DB::table('recruitment_application_stage_history')->whereIn('application_id', $this->ids)->where('rejection_reason', 'position_filled')->count());
        $this->assertSame(2, DB::table('notifications')->where('type', 'application.rejected')->count());
    }

    public function test_a_wrong_bulk_rejection_changes_nothing(): void
    {
        $url = "/company/jobs/{$this->job}/applications/bulk-reject";

        $this->api('POST', $url, ['application_ids' => $this->ids, 'reason' => 'other'], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-rejection');
        $this->api('POST', $url, ['application_ids' => [], 'reason' => 'position_filled'], $this->recruiterToken)->assertStatus(422);
        $this->api('POST', $url, ['application_ids' => [$this->ids[0], $this->ids[0]], 'reason' => 'position_filled'], $this->recruiterToken)->assertStatus(422);
        $this->api('POST', $url, ['application_ids' => [...$this->ids, 999999], 'reason' => 'position_filled'], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-applications');

        $this->assertSame(['applied', 'applied', 'applied'], $this->stageTypes());
    }

    public function test_applications_of_another_job_are_refused_and_an_unassigned_job_is_not_found(): void
    {
        $other = $this->insertJob('Frontend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $unassigned = $this->insertJob('Designer', 'active', [$this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']);

        $this->api('POST', "/company/jobs/{$other}/applications/bulk-reject", ['application_ids' => $this->ids, 'reason' => 'position_filled'], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-applications');
        $this->api('POST', "/company/jobs/{$unassigned}/applications/bulk-reject", ['application_ids' => $this->ids, 'reason' => 'position_filled'], $this->recruiterToken)->assertNotFound();
    }

    public function test_the_selection_is_capped_by_the_setting(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(RecruitmentSettings::MAX_BULK_APPLICATIONS, 2, null);

        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-reject", ['application_ids' => $this->ids, 'reason' => 'position_filled'], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:too-many-applications');
        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-reject", ['application_ids' => array_slice($this->ids, 0, 2), 'reason' => 'position_filled'], $this->recruiterToken)->assertOk();
    }

    // ----- bulk move -----

    public function test_a_bulk_move_moves_each_application_and_skips_the_one_already_there(): void
    {
        $screening = $this->stage('screening');
        $this->api('POST', "/company/applications/{$this->ids[1]}/move", ['stage_id' => $screening], $this->recruiterToken)->assertOk();

        $this->bulkMove(['application_ids' => [$this->ids[1], $this->ids[0], $this->ids[2]], 'stage_id' => $screening])
            ->assertOk()
            ->assertJsonPath('data.0.code', 'already-in-stage')
            ->assertJsonPath('data.1.status', 'moved')
            ->assertJsonPath('data.2.status', 'moved');

        $this->assertSame(['screening', 'screening', 'screening'], $this->stageTypes());
    }

    public function test_a_bulk_move_refuses_a_wrong_stage_or_input_for_the_stage(): void
    {
        $this->bulkMove(['application_ids' => $this->ids, 'stage_id' => $this->stage('rejected')])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-stage');
        $this->bulkMove(['application_ids' => $this->ids, 'stage_id' => 999999])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-stage');
        $this->bulkMove(['application_ids' => $this->ids, 'stage_id' => $this->stage('screening'), 'interview' => $this->baseSchedule()])
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-schedule');
        $this->bulkMove(['application_ids' => $this->ids, 'stage_id' => $this->stage('screening'), 'offer' => $this->offerDetails()])
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer-input');

        $this->assertSame(['applied', 'applied', 'applied'], $this->stageTypes());
    }

    // ----- bulk move to an interview stage -----

    public function test_a_bulk_move_to_interview_hands_out_slots_in_the_order_of_the_selection(): void
    {
        $order = [$this->ids[2], $this->ids[0], $this->ids[1]];

        $this->bulkMove(['application_ids' => $order, 'stage_id' => $this->stage('interview'), 'interview' => $this->baseSchedule(['gap_minutes' => 15])])
            ->assertOk()->assertJsonCount(3, 'data');

        $this->assertSame(['interview', 'interview', 'interview'], $this->stageTypes());
        $times = DB::table('recruitment_interviews')->whereIn('application_id', $order)->get()->mapWithKeys(
            fn (object $row): array => [(int) $row->application_id => (new \DateTimeImmutable($row->starts_at.' UTC'))->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('H:i')],
        )->all();
        $this->assertEquals([$order[0] => '09:00', $order[1] => '10:15', $order[2] => '11:30'], $times);
        // The interview notice replaces the stage notice: one message per move.
        $this->assertSame(0, DB::table('notifications')->where('type', 'application.moved_to_interview')->count());
        $this->assertSame(3, DB::table('notifications')->where('type', 'interview.scheduled')->count());
    }

    public function test_slots_roll_over_to_the_next_working_day_and_skip_the_interviewers_other_interviews(): void
    {
        $this->bulkMove(['application_ids' => [$this->ids[0]], 'stage_id' => $this->stage('interview'), 'interview' => $this->baseSchedule()])->assertOk();
        $this->assertSame(['10-12 09:00'], $this->slots());

        // The first slot of the next bulk move would be 09:00, the interviewer's busy hour, so it starts at 10:00; the window ends at 11:00, so the third goes to Tuesday.
        $this->bulkMove(['application_ids' => [$this->ids[1], $this->ids[2]], 'stage_id' => $this->stage('interview'), 'interview' => $this->baseSchedule(['day_end' => '11:00'])])->assertOk();

        $this->assertSame(['10-12 09:00', '10-12 10:00', '10-13 09:00'], $this->slots());
    }

    public function test_a_skipped_application_leaves_no_hole_in_the_slots(): void
    {
        $interview = $this->stage('interview');
        $this->api('POST', "/company/applications/{$this->ids[0]}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();

        $this->bulkMove(['application_ids' => $this->ids, 'stage_id' => $interview, 'interview' => $this->baseSchedule()])
            ->assertOk()->assertJsonPath('data.0.code', 'application-final');

        $this->assertSame(['10-12 09:00', '10-12 10:00'], $this->slots());
    }

    public function test_an_application_with_an_active_interview_needs_none_and_the_others_need_a_schedule(): void
    {
        $interview = $this->stage('interview');
        $this->api('POST', "/company/applications/{$this->ids[0]}/move", ['stage_id' => $interview, 'interview' => $this->interviewSchedule()], $this->recruiterToken)->assertOk();
        $this->api('POST', "/company/applications/{$this->ids[0]}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertOk();

        $this->bulkMove(['application_ids' => $this->ids, 'stage_id' => $interview])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'moved')
            ->assertJsonPath('data.1.code', 'schedule-required')
            ->assertJsonPath('data.2.code', 'schedule-required');
    }

    public function test_a_wrong_base_schedule_changes_nothing(): void
    {
        $body = ['application_ids' => $this->ids, 'stage_id' => $this->stage('interview')];

        $this->bulkMove([...$body, 'interview' => $this->baseSchedule(['starts_at' => '2026-10-01T02:00:00Z'])])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview');
        $this->bulkMove([...$body, 'interview' => $this->baseSchedule(['interviewer_id' => $this->memberWith(['applications.view'], 'viewer@example.test')['id']])])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interviewer');
        $this->bulkMove([...$body, 'interview' => $this->baseSchedule(['duration_minutes' => 120, 'day_end' => '10:00'])])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview');
        $this->bulkMove([...$body, 'interview' => $this->baseSchedule(['day_start' => '17:00', 'day_end' => '09:00'])])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview');
        $this->bulkMove([...$body, 'interview' => $this->baseSchedule(['gap_minutes' => 500])])->assertStatus(422);

        $this->assertSame(['applied', 'applied', 'applied'], $this->stageTypes());
        $this->assertSame(0, DB::table('recruitment_interviews')->count());
    }

    // ----- bulk move to an offer stage -----

    public function test_a_bulk_move_to_an_offer_stage_sends_the_shared_offer_to_each_applicant(): void
    {
        $this->bulkMove(['application_ids' => $this->ids, 'stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails(['salary' => 12000000])])
            ->assertOk()->assertJsonPath('data.2.status', 'moved');

        $this->assertSame(['offer', 'offer', 'offer'], $this->stageTypes());
        $offers = DB::table('recruitment_offers')->whereIn('application_id', $this->ids)->get();
        $this->assertCount(3, $offers);
        $this->assertSame([12000000], $offers->pluck('salary')->map(fn ($salary): int => (int) $salary)->unique()->values()->all());
        $this->assertSame(['Backend Developer'], $offers->pluck('position')->unique()->values()->all());
    }

    public function test_a_bulk_move_to_an_offer_stage_without_an_offer_skips_those_without_one(): void
    {
        $this->bulkMove(['application_ids' => $this->ids, 'stage_id' => $this->stage('offer')])
            ->assertOk()->assertJsonPath('data.0.code', 'offer-required')->assertJsonPath('data.2.code', 'offer-required');

        $this->assertSame(['applied', 'applied', 'applied'], $this->stageTypes());
    }

    public function test_a_wrong_shared_offer_changes_nothing(): void
    {
        $this->bulkMove(['application_ids' => $this->ids, 'stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails(['salary' => 0])])
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer');

        $this->assertSame(['applied', 'applied', 'applied'], $this->stageTypes());
        $this->assertSame(0, DB::table('recruitment_offers')->count());
    }
}
