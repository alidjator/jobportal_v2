<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-candidate-experience.md` sections 3 and 5: the to-do list and the agenda of a job seeker. */
final class ApplicantTasksAndAgendaTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $otherJob;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->otherJob = $this->insertJob('Data Analyst', 'active', [$this->adminId], attributes: ['deadline' => '2026-12-31']);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function tasks(?string $token = null): TestResponse
    {
        return $this->api('GET', '/me/tasks', token: $token ?? $this->seekerToken);
    }

    private function agenda(array $query = [], ?string $token = null): TestResponse
    {
        return $this->api('GET', '/me/agenda?'.http_build_query($query), token: $token ?? $this->seekerToken);
    }

    /** @return list<string> */
    private function taskKeys(?string $token = null): array
    {
        return array_map(static fn (array $task): string => $task['key'].':'.$task['subject_id'], $this->tasks($token)->assertOk()->json('data'));
    }

    /** @return list<string> */
    private function agendaIds(array $query = [], ?string $token = null): array
    {
        return array_column($this->agenda($query, $token)->assertOk()->json('data'), 'id');
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function interview(string $startsAt): int
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", [
            'stage_id' => $this->stage('interview'), 'interview' => $this->interviewSchedule(['starts_at' => $startsAt]),
        ], $this->recruiterToken)->assertOk();

        return (int) DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->orderByDesc('id')->value('id');
    }

    private function offer(string $status, string $expiresAt, string $startDate = '2026-11-02'): int
    {
        return (int) DB::table('recruitment_offers')->insertGetId([
            'application_id' => $this->applicationId, 'position' => 'Backend Developer', 'salary' => 10000000, 'start_date' => $startDate, 'expires_at' => $expiresAt, 'created_by' => $this->recruiterId,
            'status' => $status, 'active_key' => $status === 'pending' ? $this->applicationId : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function invitation(string $startsAt, string $dueAt, string $status = 'invited'): int
    {
        $test = (int) DB::table('assessment_tests')->insertGetId([
            'kind' => 'skill', 'company_id' => $this->companyId, 'title' => 'Case study', 'measured_capabilities' => json_encode([]), 'status' => 'published', 'created_by_user_id' => $this->adminId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('assessment_invitations')->insertGetId([
            'application_id' => $this->applicationId, 'company_id' => $this->companyId, 'job_id' => $this->job, 'applicant_user_id' => $this->seekerId, 'test_id' => $test,
            'invited_by_user_id' => $this->adminId, 'starts_at' => $startsAt, 'due_at' => $dueAt, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function jobInvitation(string $status = 'sent'): int
    {
        return (int) DB::table('jobinvitation_invitations')->insertGetId([
            'company_id' => $this->companyId, 'job_id' => $this->otherJob, 'user_id' => $this->seekerId, 'invited_by_user_id' => $this->adminId, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function check(array $extra = []): int
    {
        $type = (int) DB::table('recruitment_check_types')->insertGetId(['company_id' => $this->companyId, 'name' => 'MCU', 'kind' => 'medical', 'requires_consent' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table('recruitment_application_checks')->insertGetId([
            'application_id' => $this->applicationId, 'check_type_id' => $type, 'status' => 'requested', 'consent_required' => true, 'requested_by' => $this->recruiterId,
            'created_at' => now(), 'updated_at' => now(), ...$extra,
        ]);
    }

    private function switchChecks(bool $on): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('recruitment.pre_employment_checks_enabled', $on, null);
    }

    // ----- who -----

    public function test_only_a_job_seeker_has_a_list_and_an_agenda(): void
    {
        $this->tasks($this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        $this->agenda(token: $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/me/tasks')->assertStatus(401);
        $this->getJson('/api/v1/me/agenda')->assertStatus(401);
    }

    public function test_nothing_of_another_job_seeker_shows(): void
    {
        $this->interview('2026-10-12T02:00:00Z');
        $this->offer('pending', '2026-10-20 10:00:00');
        $this->jobInvitation();

        $other = $this->tokenFor($this->seeker('other@example.test'));

        $this->assertSame([], $this->taskKeys($other));
        $this->assertSame([], $this->agendaIds(token: $other));
    }

    // ----- the to-do list -----

    public function test_an_interview_waits_for_the_answer_until_it_is_confirmed_or_has_started(): void
    {
        $interview = $this->interview('2026-10-12T02:00:00Z');
        $this->assertSame(["interview_confirm:{$interview}"], $this->taskKeys());

        $first = $this->tasks()->json('data.0');
        $this->assertSame($this->applicationId, $first['application_id']);
        $this->assertSame('Backend Developer', $first['job']['title']);
        $this->assertSame('2026-10-12T02:00:00+00:00', $first['due_at']);
        $this->assertNotNull($first['company_name']);

        DB::table('recruitment_interviews')->where('id', $interview)->update(['status' => 'confirmed']);
        $this->assertSame([], $this->taskKeys());

        DB::table('recruitment_interviews')->where('id', $interview)->update(['status' => 'pending', 'starts_at' => '2026-10-05 08:00:00', 'ends_at' => '2026-10-05 09:30:00']);
        $this->assertSame([], $this->taskKeys(), 'an interview that has started cannot be confirmed');
    }

    public function test_an_offer_waits_for_the_answer_until_its_deadline(): void
    {
        $offer = $this->offer('pending', '2026-10-20 10:00:00');
        $this->assertSame(["offer_answer:{$offer}"], $this->taskKeys());

        DB::table('recruitment_offers')->where('id', $offer)->update(['expires_at' => '2026-10-05 08:59:00']);
        $this->assertSame([], $this->taskKeys());

        DB::table('recruitment_offers')->where('id', $offer)->update(['expires_at' => '2026-10-20 10:00:00', 'status' => 'accepted', 'active_key' => null]);
        $this->assertSame([], $this->taskKeys());
    }

    public function test_a_test_waits_until_it_is_submitted_or_past_due(): void
    {
        $invitation = $this->invitation('2026-10-05 00:00:00', '2026-10-09 10:00:00');
        $this->assertSame(["assessment_take:{$invitation}"], $this->taskKeys());

        DB::table('assessment_invitations')->where('id', $invitation)->update(['status' => 'started']);
        $this->assertSame(["assessment_take:{$invitation}"], $this->taskKeys());

        DB::table('assessment_invitations')->where('id', $invitation)->update(['status' => 'submitted']);
        $this->assertSame([], $this->taskKeys());

        DB::table('assessment_invitations')->where('id', $invitation)->update(['status' => 'invited', 'due_at' => '2026-10-05 08:00:00', 'starts_at' => '2026-10-01 00:00:00']);
        $this->assertSame([], $this->taskKeys());
    }

    public function test_an_invitation_to_apply_waits_while_the_job_is_live_and_has_no_due_time(): void
    {
        $invitation = $this->jobInvitation();
        $this->assertSame(["job_invitation:{$invitation}"], $this->taskKeys());
        $this->assertNull($this->tasks()->json('data.0.due_at'));
        $this->assertNull($this->tasks()->json('data.0.application_id'));

        DB::table('jobinvitation_invitations')->where('id', $invitation)->update(['status' => 'viewed']);
        $this->assertSame(["job_invitation:{$invitation}"], $this->taskKeys());

        DB::table('jobinvitation_invitations')->where('id', $invitation)->update(['status' => 'declined']);
        $this->assertSame([], $this->taskKeys());

        DB::table('jobinvitation_invitations')->where('id', $invitation)->update(['status' => 'sent']);
        DB::table('job_jobs')->where('id', $this->otherJob)->update(['status' => 'closed']);
        $this->assertSame([], $this->taskKeys());
    }

    public function test_a_check_waits_for_the_consent_only_while_the_feature_is_on(): void
    {
        $check = $this->check();

        $this->assertSame([], $this->taskKeys());

        $this->switchChecks(true);
        $this->assertSame(["check_consent:{$check}"], $this->taskKeys());
        $this->assertNull($this->tasks()->json('data.0.due_at'));

        DB::table('recruitment_application_checks')->where('id', $check)->update(['consented_at' => now(), 'status' => 'consented']);
        $this->assertSame([], $this->taskKeys());

        DB::table('recruitment_application_checks')->where('id', $check)->update(['consented_at' => null, 'status' => 'requested', 'consent_refused_at' => now()]);
        $this->assertSame([], $this->taskKeys(), 'a refusal is answered');
    }

    public function test_the_ones_with_a_due_time_come_first_and_the_rest_newest_first(): void
    {
        $late = $this->offer('pending', '2026-10-30 10:00:00');
        $soon = $this->invitation('2026-10-05 00:00:00', '2026-10-08 10:00:00');
        $invitation = $this->jobInvitation();

        $this->assertSame(["assessment_take:{$soon}", "offer_answer:{$late}", "job_invitation:{$invitation}"], $this->taskKeys());
        $this->assertSame(3, $this->tasks()->json('meta.total'));
    }

    public function test_a_rejected_or_withdrawn_application_gives_no_task_and_no_date(): void
    {
        $this->interview('2026-10-12T02:00:00Z');
        $this->offer('pending', '2026-10-20 10:00:00');
        $this->assertCount(2, $this->taskKeys());

        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['stage_type' => 'rejected']);

        $this->assertSame([], $this->taskKeys());
        $this->assertSame([], $this->agendaIds());

        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['stage_type' => 'withdrawn']);
        $this->assertSame([], $this->taskKeys());
        $this->assertSame([], $this->agendaIds());
    }

    // ----- the agenda -----

    public function test_the_range_is_a_few_jakarta_days_of_at_most_42_and_starts_today_by_default(): void
    {
        $this->agenda(['from' => '2026-10-01', 'to' => '2026-11-11'])->assertOk();
        $this->agenda(['from' => '2026-10-01', 'to' => '2026-11-12'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-calendar-range')->assertJsonStructure(['errors' => ['to']]);
        $this->agenda(['from' => '2026-10-10', 'to' => '2026-10-09'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-calendar-range');
        $this->agenda(['from' => '10-10-2026'])->assertStatus(422)->assertJsonValidationErrors(['from']);

        // Without a range: today and the 29 days after (30 days in all).
        $inside = $this->interview('2026-11-02T02:00:00Z');
        $this->assertSame(["interview:{$inside}"], $this->agendaIds());

        DB::table('recruitment_interviews')->where('id', $inside)->update(['starts_at' => '2026-11-05 02:00:00', 'ends_at' => '2026-11-05 03:00:00']);
        $this->assertSame([], $this->agendaIds());
    }

    public function test_an_interview_shows_its_format_and_place_but_not_the_meeting_link(): void
    {
        $interview = $this->interview('2026-10-12T02:00:00Z');
        DB::table('recruitment_interviews')->where('id', $interview)->update(['format' => 'onsite', 'location' => 'Jl. Sudirman 1', 'proposed_starts_at' => '2026-10-13 02:00:00']);

        $item = $this->agenda()->assertOk()->json('data.0');

        $this->assertSame("interview:{$interview}", $item['id']);
        $this->assertSame('interview', $item['type']);
        $this->assertSame('2026-10-12T02:00:00+00:00', $item['starts_at']);
        $this->assertSame('2026-10-12T03:00:00+00:00', $item['ends_at']);
        $this->assertFalse($item['all_day']);
        $this->assertSame('Backend Developer', $item['job']['title']);
        $this->assertSame('onsite', $item['details']['format']);
        $this->assertSame('Jl. Sudirman 1', $item['details']['location']);
        $this->assertFalse($item['details']['has_room']);
        $this->assertSame('2026-10-13T02:00:00+00:00', $item['details']['proposed_starts_at']);
        $this->assertArrayNotHasKey('meeting_url', $item['details']);

        DB::table('recruitment_interviews')->where('id', $interview)->update(['status' => 'cancelled']);
        $this->assertSame([], $this->agendaIds());
    }

    public function test_an_offer_shows_its_deadline_and_a_hire_the_join_date_for_the_whole_day(): void
    {
        $offer = $this->offer('pending', '2026-10-20 10:00:00', '2026-11-02');
        $this->assertSame(["offer_deadline:{$offer}"], $this->agendaIds());
        $this->assertSame('2026-10-20T10:00:00+00:00', $this->agenda()->json('data.0.starts_at'));

        DB::table('recruitment_offers')->where('id', $offer)->update(['status' => 'accepted', 'active_key' => null]);
        $this->assertSame([], $this->agendaIds(), 'an accepted offer is a join date only once the applicant is hired');

        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['stage_type' => 'hired']);
        $item = $this->agenda(['from' => '2026-10-30', 'to' => '2026-11-05'])->assertOk()->json('data.0');

        $this->assertSame("join_date:{$offer}", $item['id']);
        $this->assertSame('2026-11-02', $item['starts_at']);
        $this->assertTrue($item['all_day']);
        $this->assertNull($item['ends_at']);
    }

    public function test_a_test_shows_its_window_until_it_is_submitted(): void
    {
        $invitation = $this->invitation('2026-10-06 00:00:00', '2026-10-09 10:00:00');

        $item = $this->agenda()->assertOk()->json('data.0');
        $this->assertSame("assessment_window:{$invitation}", $item['id']);
        $this->assertSame('2026-10-06T00:00:00+00:00', $item['starts_at']);
        $this->assertSame('2026-10-09T10:00:00+00:00', $item['ends_at']);

        DB::table('assessment_invitations')->where('id', $invitation)->update(['status' => 'submitted']);
        $this->assertSame([], $this->agendaIds());
    }

    public function test_a_check_shows_its_schedule_while_it_is_open_and_the_feature_is_on(): void
    {
        $check = $this->check(['scheduled_at' => '2026-10-15 03:00:00', 'place' => 'Klinik Sehat', 'vendor' => 'Klinik Sehat']);
        $this->assertSame([], $this->agendaIds());

        $this->switchChecks(true);
        $item = $this->agenda()->assertOk()->json('data.0');
        $this->assertSame("check:{$check}", $item['id']);
        $this->assertSame('MCU', $item['details']['name']);
        $this->assertSame('Klinik Sehat', $item['details']['place']);
        $this->assertTrue($item['details']['awaiting_consent']);
        $this->assertArrayNotHasKey('outcome', $item['details']);

        DB::table('recruitment_application_checks')->where('id', $check)->update(['status' => 'completed']);
        $this->assertSame([], $this->agendaIds());
    }

    public function test_dates_come_earliest_first(): void
    {
        // The interview goes first: moving the application to the interview stage closes an offer that is still pending.
        $interview = $this->interview('2026-10-07T02:00:00Z');
        $offer = $this->offer('pending', '2026-10-08 10:00:00');
        $invitation = $this->invitation('2026-10-09 00:00:00', '2026-10-12 10:00:00');

        $this->assertSame(["interview:{$interview}", "offer_deadline:{$offer}", "assessment_window:{$invitation}"], $this->agendaIds());
    }

    // ----- the dashboard -----

    public function test_the_dashboard_carries_the_first_five_tasks_and_the_next_dates(): void
    {
        $this->interview('2026-10-07T02:00:00Z');
        $this->offer('pending', '2026-10-20 10:00:00');
        $this->invitation('2026-10-06 00:00:00', '2026-10-30 10:00:00');
        $this->jobInvitation();

        $cards = $this->api('GET', '/me/dashboard', token: $this->seekerToken)->assertOk()->json('data.cards');

        $this->assertSame(4, $cards['todo']['total']);
        $this->assertSame(['interview_confirm', 'offer_answer', 'assessment_take', 'job_invitation'], array_column($cards['todo']['items'], 'key'));
        $this->assertSame('Backend Developer', $cards['todo']['items'][0]['job_title']);
        // The window of the test opens first, then the interview; the deadline of the offer is more than 7 days away.
        $this->assertSame(2, $cards['agenda']['next_7_days']);
        $this->assertSame(['assessment_window', 'interview', 'offer_deadline'], array_column($cards['agenda']['items'], 'type'));
    }
}
