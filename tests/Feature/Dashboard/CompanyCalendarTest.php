<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interview-calendar.md` section 1: the recruitment calendar. */
final class CompanyCalendarTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $adminJob;

    private int $applicationId;

    private int $otherApplicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->adminJob = $this->insertJob('Data Analyst', 'active', [$this->adminId], attributes: ['deadline' => '2026-12-31']);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
        $this->otherApplicationId = (int) $this->apply($this->adminJob)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function calendar(array $query = [], ?string $token = null): TestResponse
    {
        return $this->api('GET', '/company/calendar?'.http_build_query(['from' => '2026-10-01', 'to' => '2026-10-31', ...$query]), token: $token ?? $this->recruiterToken);
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function interview(int $applicationId, string $startsAt, array $extra = []): int
    {
        $token = $applicationId === $this->applicationId ? $this->recruiterToken : $this->adminToken;
        $this->api('POST', "/company/applications/{$applicationId}/move", [
            'stage_id' => $this->stage('interview'), 'interview' => $this->interviewSchedule(['starts_at' => $startsAt, 'interviewer_id' => $applicationId === $this->applicationId ? $this->recruiterId : $this->adminId, ...$extra]),
        ], $token)->assertNoContent();

        return (int) DB::table('recruitment_interviews')->where('application_id', $applicationId)->orderByDesc('id')->value('id');
    }

    private function offer(int $applicationId, string $status, string $expiresAt, string $startDate, int $createdBy): int
    {
        return (int) DB::table('recruitment_offers')->insertGetId([
            'application_id' => $applicationId, 'position' => 'Backend Developer', 'salary' => 10000000, 'start_date' => $startDate, 'expires_at' => $expiresAt, 'created_by' => $createdBy,
            'status' => $status, 'active_key' => $status === 'pending' ? $applicationId : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return list<string> */
    private function ids(TestResponse $response): array
    {
        return array_column($response->assertOk()->json('data'), 'id');
    }

    // ----- the range -----

    public function test_the_range_is_a_few_jakarta_days_of_at_most_42(): void
    {
        $this->calendar(['from' => '2026-10-01', 'to' => '2026-11-11'])->assertOk();
        $this->calendar(['from' => '2026-10-01', 'to' => '2026-11-12'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-calendar-range')->assertJsonStructure(['errors' => ['to']]);
        $this->calendar(['from' => '2026-10-10', 'to' => '2026-10-09'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-calendar-range');
        $this->calendar(['from' => '2026-10-10', 'to' => '2026-10-10'])->assertOk();
        $this->calendar(['from' => '10-10-2026'])->assertStatus(422)->assertJsonValidationErrors(['from']);
        $this->api('GET', '/company/calendar', token: $this->recruiterToken)->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->calendar(['filter' => ['type' => 'interview,party']])->assertStatus(422)->assertJsonValidationErrors(['filter.type']);
    }

    public function test_only_a_member_who_may_read_applicants_has_a_calendar(): void
    {
        $this->calendar(token: $this->seekerToken)->assertForbidden();
        $none = $this->memberWith(['company.profile'], 'none@example.test');
        $this->calendar(token: $none['token'])->assertForbidden()->assertJsonPath('permission', 'applications.view');
    }

    // ----- interviews -----

    public function test_an_interview_shows_with_its_people_and_what_the_member_may_do(): void
    {
        $id = $this->interview($this->applicationId, '2026-10-12T02:00:00Z', ['participant_ids' => [$this->adminId], 'meeting_url' => 'https://meet.example.test/secret']);

        $event = $this->calendar()->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.truncated', false)->json('data.0');

        $this->assertSame("interview:{$id}", $event['id']);
        $this->assertSame('interview', $event['type']);
        $this->assertSame('Siti Rahma - Backend Developer', $event['title']);
        $this->assertSame('2026-10-12T02:00:00+00:00', $event['starts_at']);
        $this->assertSame('2026-10-12T03:00:00+00:00', $event['ends_at']);
        $this->assertFalse($event['all_day']);
        $this->assertSame('pending', $event['status']);
        $this->assertSame($this->applicationId, $event['application_id']);
        $this->assertSame(['id' => $this->job, 'title' => 'Backend Developer'], $event['job']);
        $this->assertSame('Siti Rahma', $event['candidate_name']);
        $this->assertSame('online', $event['details']['format']);
        $this->assertTrue($event['details']['has_meeting_link']);
        $this->assertStringNotContainsString('secret', json_encode($event));
        $this->assertSame($this->recruiterId, $event['details']['interviewer']['id']);
        $this->assertSame([$this->adminId], array_column($event['details']['panel'], 'id'));
        $this->assertTrue($event['involves_me']);
        $this->assertSame(['view_application', 'edit', 'edit_note', 'cancel', 'download_ics'], $event['actions']);

        // The admin sees every job and is on the panel.
        $admin = collect($this->calendar(token: $this->adminToken)->json('data'))->firstWhere('id', "interview:{$id}");
        $this->assertTrue($admin['involves_me']);

        // A recruiter who is not assigned to the job sees nothing of it.
        $this->calendar(token: $this->secondRecruiterToken)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_scorecard_comes_once_the_interview_has_started_and_edits_end_with_it(): void
    {
        $this->interview($this->applicationId, '2026-10-12T02:00:00Z');
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();

        Carbon::setTestNow('2026-10-12 04:00:00');
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->assertSame(['view_application', 'edit', 'edit_note', 'cancel', 'scorecard', 'download_ics'], $this->calendar()->json('data.0.actions'));

        DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->update(['status' => 'completed']);
        $this->assertSame(['view_application', 'scorecard', 'download_ics'], $this->calendar()->json('data.0.actions'));
        $this->assertSame('completed', $this->calendar()->json('data.0.status'));
    }

    public function test_a_declined_or_cancelled_interview_is_not_shown(): void
    {
        $this->interview($this->applicationId, '2026-10-12T02:00:00Z');

        foreach (['declined', 'cancelled'] as $status) {
            DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->update(['status' => $status, 'active_key' => null]);
            $this->calendar()->assertOk()->assertJsonCount(0, 'data');
        }
    }

    public function test_the_window_is_in_jakarta_days_and_catches_an_interview_that_overlaps_it(): void
    {
        // 2026-10-31 18:00 UTC is 01:00 on 1 November in Jakarta.
        $id = $this->interview($this->applicationId, '2026-10-31T18:00:00Z');

        $this->assertSame([], $this->ids($this->calendar(['from' => '2026-10-25', 'to' => '2026-10-31'])));
        $this->assertSame(["interview:{$id}"], $this->ids($this->calendar(['from' => '2026-11-01', 'to' => '2026-11-01'])));
        $this->assertSame(["interview:{$id}"], $this->ids($this->calendar(['from' => '2026-10-25', 'to' => '2026-11-01'])));

        // An interview that began the evening before and runs into the window still shows.
        DB::table('recruitment_interviews')->where('id', $id)->update(['starts_at' => '2026-10-31 16:30:00', 'ends_at' => '2026-10-31 17:30:00']);
        $this->assertSame(["interview:{$id}"], $this->ids($this->calendar(['from' => '2026-11-01', 'to' => '2026-11-01'])));
    }

    // ----- offers and tests -----

    public function test_an_offer_shows_its_answer_deadline_and_its_start_date(): void
    {
        $pending = $this->offer($this->applicationId, 'pending', '2026-10-14 10:00:00', '2026-11-16', $this->recruiterId);
        $accepted = $this->offer($this->otherApplicationId, 'accepted', '2026-10-10 10:00:00', '2026-10-20', $this->adminId);

        $deadline = collect($this->calendar()->json('data'))->firstWhere('id', "offer_deadline:{$pending}");
        $this->assertSame('2026-10-14T10:00:00+00:00', $deadline['starts_at']);
        $this->assertNull($deadline['ends_at']);
        $this->assertFalse($deadline['all_day']);
        $this->assertSame('pending', $deadline['status']);
        $this->assertTrue($deadline['involves_me']);
        $this->assertSame(['view_application'], $deadline['actions']);
        $this->assertSame('Backend Developer', $deadline['details']['position']);

        // The accepted offer is on the admin's job: the admin sees its start date as a whole day, the recruiter does not see it.
        $start = collect($this->calendar(token: $this->adminToken)->json('data'))->firstWhere('id', "start_date:{$accepted}");
        $this->assertSame('2026-10-20', $start['starts_at']);
        $this->assertTrue($start['all_day']);
        $this->assertSame([], array_filter($this->ids($this->calendar()), fn (string $id): bool => str_starts_with($id, 'start_date')));

        // The pending offer starts in November: its start date is not on the calendar until it is accepted, and the deadline is not in a November window.
        $this->assertSame([], $this->ids($this->calendar(['from' => '2026-11-10', 'to' => '2026-11-20'])));
        DB::table('recruitment_offers')->where('id', $pending)->update(['status' => 'accepted', 'active_key' => null]);
        $this->assertSame(["start_date:{$pending}"], $this->ids($this->calendar(['from' => '2026-11-10', 'to' => '2026-11-20'])));
    }

    public function test_a_test_shows_its_due_time_until_it_is_submitted(): void
    {
        $test = (int) DB::table('assessment_tests')->insertGetId([
            'kind' => 'skill', 'company_id' => $this->companyId, 'title' => 'Case study', 'measured_capabilities' => json_encode([]), 'status' => 'published', 'created_by_user_id' => $this->adminId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $invitation = (int) DB::table('assessment_invitations')->insertGetId([
            'application_id' => $this->applicationId, 'company_id' => $this->companyId, 'job_id' => $this->job, 'applicant_user_id' => $this->seekerId, 'test_id' => $test,
            'invited_by_user_id' => $this->adminId, 'starts_at' => '2026-10-06 00:00:00', 'due_at' => '2026-10-09 10:00:00', 'status' => 'invited', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('assessment_invitation_assessors')->insert(['invitation_id' => $invitation, 'user_id' => $this->recruiterId]);

        $event = collect($this->calendar()->json('data'))->firstWhere('id', "assessment_due:{$invitation}");
        $this->assertSame('2026-10-09T10:00:00+00:00', $event['starts_at']);
        $this->assertSame('Siti Rahma - Backend Developer', $event['title']);
        $this->assertTrue($event['involves_me'], 'the assessor is involved');
        $this->assertSame(['view_application'], $event['actions']);

        DB::table('assessment_invitations')->where('id', $invitation)->update(['status' => 'submitted']);
        $this->assertSame([], $this->ids($this->calendar()));
    }

    // ----- filters, order and scope -----

    public function test_events_come_earliest_first_and_the_filters_narrow_them(): void
    {
        $late = $this->interview($this->applicationId, '2026-10-20T03:00:00Z');
        $early = $this->interview($this->otherApplicationId, '2026-10-08T03:00:00Z');
        $offer = $this->offer($this->applicationId, 'pending', '2026-10-14 10:00:00', '2026-11-16', $this->adminId);

        $all = $this->calendar(token: $this->adminToken);
        $this->assertSame(["interview:{$early}", "offer_deadline:{$offer}", "interview:{$late}"], array_column($all->json('data'), 'id'));

        $this->assertSame(["interview:{$early}", "interview:{$late}"], $this->ids($this->calendar(['filter' => ['type' => 'interview']], $this->adminToken)));
        $this->assertSame(["offer_deadline:{$offer}"], $this->ids($this->calendar(['filter' => ['type' => 'offer_deadline,start_date']], $this->adminToken)));
        $this->assertSame(["interview:{$late}", "offer_deadline:{$offer}"], array_reverse($this->ids($this->calendar(['filter' => ['job_id' => $this->job]], $this->adminToken))));

        // "Mine": the admin is the interviewer of one, and sent the offer; the late interview is the recruiter's.
        $this->assertSame(["interview:{$early}", "offer_deadline:{$offer}"], $this->ids($this->calendar(['filter' => ['mine' => 'true']], $this->adminToken)));
        $this->assertSame(["interview:{$late}"], $this->ids($this->calendar(['filter' => ['mine' => '1']])));
    }

    public function test_a_hiring_manager_bound_to_a_job_sees_only_what_reached_the_boundary(): void
    {
        $manager = $this->memberWith(['applications.view', 'applications.review', 'interviews.manage'], 'manager@example.test');
        DB::table('job_assignments')->insert(['job_id' => $this->job, 'user_id' => $manager['id'], 'role' => 'hiring_manager', 'created_at' => now()]);
        $this->offer($this->applicationId, 'pending', '2026-10-14 10:00:00', '2026-11-16', $this->recruiterId);

        $this->assertSame([], $this->ids($this->calendar(token: $manager['token'])));

        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['hiring_manager_visible_at' => now()]);
        $this->assertCount(1, $this->ids($this->calendar(token: $manager['token'])));
    }
}
