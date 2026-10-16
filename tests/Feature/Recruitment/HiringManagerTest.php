<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-hiring-managers.md` sections 1 and 2: the role on the job, the review permission and the stage boundary with its marker. */
final class HiringManagerTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $managerId;

    private string $managerToken;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->verifyTheCompany();

        $manager = $this->memberWith(['applications.view', 'applications.review', 'interviews.manage'], 'manager@example.test');
        $this->managerId = $manager['id'];
        $this->managerToken = $manager['token'];

        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId, $this->managerId], attributes: ['deadline' => '2026-12-31']);
        DB::table('job_assignments')->where('job_id', $this->job)->where('user_id', $this->managerId)->update(['role' => 'hiring_manager']);

        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
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

    private function move(string $type, array $extra = []): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage($type), ...$extra], $this->recruiterToken)->assertNoContent();
    }

    private function moveToInterview(array $schedule = []): void
    {
        $this->move('interview', ['interview' => $this->interviewSchedule($schedule)]);
    }

    private function marker(): ?string
    {
        $value = DB::table('recruitment_applications')->where('id', $this->applicationId)->value('hiring_manager_visible_at');

        return $value === null ? null : (string) $value;
    }

    private function managerList(): array
    {
        return $this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->managerToken)->assertOk()->json();
    }

    // ----- the role on the job -----

    public function test_an_assignee_has_a_role_on_the_job_and_the_plain_list_keeps_the_roles(): void
    {
        $this->api('GET', "/company/jobs/{$this->job}/assignees")->assertOk()
            ->assertJsonPath('data.0.job_role', 'recruiter')->assertJsonPath('data.1.job_role', 'hiring_manager');

        $this->api('PUT', "/company/jobs/{$this->job}/assignees", ['assignees' => [
            ['user_id' => $this->recruiterId, 'role' => 'hiring_manager'], ['user_id' => $this->managerId],
        ]])->assertOk()->assertJsonPath('data.0.job_role', 'hiring_manager')->assertJsonPath('data.1.job_role', 'recruiter');

        // Rows come back in the order they were assigned, and the plain list keeps the roles as they are.
        $this->api('PUT', "/company/jobs/{$this->job}/assignees", ['user_ids' => [$this->managerId, $this->recruiterId]])->assertOk()
            ->assertJsonPath('data.0.user_id', $this->recruiterId)->assertJsonPath('data.0.job_role', 'hiring_manager')
            ->assertJsonPath('data.1.user_id', $this->managerId)->assertJsonPath('data.1.job_role', 'recruiter');

        $this->api('PUT', "/company/jobs/{$this->job}/assignees", ['assignees' => [['user_id' => $this->managerId, 'role' => 'owner']]])->assertStatus(422)->assertJsonValidationErrors(['assignees.0.role']);
        $this->api('PUT', "/company/jobs/{$this->job}/assignees", ['user_ids' => [$this->managerId], 'assignees' => [['user_id' => $this->managerId]]])->assertStatus(422);
    }

    // ----- the review permission -----

    public function test_managing_applicants_needs_the_review_permission_and_the_catalog_says_so(): void
    {
        $catalog = $this->api('GET', '/company/permissions')->assertOk()->json('data');
        $applicants = collect($catalog['groups'])->firstWhere('group', 'applicants')['permissions'];
        $this->assertSame(['applications.view', 'applications.manage', 'applications.review', 'applications.export', 'interviews.manage', 'offers.manage', 'assessments.send'], array_column($applicants, 'permission'));
        $this->assertTrue(collect($applicants)->firstWhere('permission', 'applications.manage')['requires_review']);
        $this->assertFalse(collect($applicants)->firstWhere('permission', 'applications.review')['requires_review']);

        $presets = collect($catalog['presets'])->keyBy('preset');
        $this->assertSame(['applications.view', 'applications.review', 'interviews.manage'], $presets['hiring_manager']['permissions']);
        $this->assertContains('applications.review', $presets['recruiter']['permissions']);
    }

    public function test_rating_and_notes_need_review_not_manage(): void
    {
        $reviewer = $this->memberWith(['applications.view', 'applications.review', 'jobs.view_all'], 'reviewer@example.test');
        $manageOnly = $this->memberWith(['applications.view', 'applications.manage', 'jobs.view_all'], 'manage-only@example.test');
        $this->move('interview', ['interview' => $this->interviewSchedule()]);

        $this->api('POST', "/company/applications/{$this->applicationId}/notes", ['body' => 'Strong'], $reviewer['token'])->assertCreated();
        $this->api('POST', "/company/applications/{$this->applicationId}/notes", ['body' => 'Strong'], $manageOnly['token'])->assertForbidden()->assertJsonPath('permission', 'applications.review');
    }

    // ----- the stage boundary -----

    public function test_a_hiring_manager_sees_an_application_only_from_the_first_interview_stage(): void
    {
        $this->assertNull($this->marker());
        $this->assertSame([], $this->managerList()['data']);
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertNotFound();
        $this->api('GET', "/company/applications/{$this->applicationId}/resume", token: $this->managerToken)->assertNotFound();
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->recruiterToken)->assertOk();

        $this->move('screening');
        $this->assertNull($this->marker());
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertNotFound();

        $this->moveToInterview();
        $this->assertNotNull($this->marker());
        $this->assertCount(1, $this->managerList()['data']);
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertOk();

        // A later rejection never hides it again.
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'other', 'note' => 'No'], $this->recruiterToken)->assertNoContent();
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertOk();
    }

    public function test_the_stage_counts_of_a_hiring_manager_hold_only_what_they_see(): void
    {
        $second = $this->seeker('budi.s@example.test', name: 'Budi Santoso');
        $secondToken = $this->tokenFor($second);
        $this->api('POST', "/jobs/{$this->job}/applications", ['resume_id' => $this->completeProfile($secondToken)], $secondToken)->assertCreated();
        $this->moveToInterview();

        $counts = collect($this->managerList()['stages'])->sum('count');
        $recruiterCounts = collect($this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->recruiterToken)->json('stages'))->sum('count');

        $this->assertSame(1, $counts);
        $this->assertSame(2, $recruiterCounts);
    }

    public function test_the_boundary_can_be_moved_and_the_qualifying_applications_appear_at_once(): void
    {
        $this->assertNull($this->marker());

        $this->api('PUT', "/company/jobs/{$this->job}/hiring-manager-boundary", ['stage_id' => $this->stage('applied')], $this->recruiterToken)->assertNoContent();
        $this->assertNotNull($this->marker());
        $this->assertSame($this->stage('applied'), (int) DB::table('job_jobs')->where('id', $this->job)->value('hiring_manager_from_stage_id'));
        $this->api('GET', "/company/jobs/{$this->job}", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.hiring_manager_from_stage_id', $this->stage('applied'));
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertOk();

        $this->api('PUT', "/company/jobs/{$this->job}/hiring-manager-boundary", ['stage_id' => null], $this->recruiterToken)->assertNoContent();
        $this->assertNull(DB::table('job_jobs')->where('id', $this->job)->value('hiring_manager_from_stage_id'));
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertOk();
    }

    public function test_a_boundary_at_a_later_stage_holds_the_application_back(): void
    {
        $this->api('PUT', "/company/jobs/{$this->job}/hiring-manager-boundary", ['stage_id' => $this->stage('offer')], $this->recruiterToken)->assertNoContent();
        $this->moveToInterview();

        $this->assertNull($this->marker());
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertNotFound();

        $this->move('offer', ['offer' => $this->offerDetails()]);
        $this->assertNotNull($this->marker());
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertOk();
    }

    public function test_the_boundary_must_be_an_active_stage_of_the_company(): void
    {
        foreach ([$this->stage('rejected'), $this->stage('withdrawn'), 999999] as $stageId) {
            $this->api('PUT', "/company/jobs/{$this->job}/hiring-manager-boundary", ['stage_id' => $stageId], $this->recruiterToken)->assertStatus(422)
                ->assertJsonPath('type', 'urn:onesis:error:invalid-stage')->assertJsonStructure(['errors' => ['stage_id']]);
        }

        $this->api('PUT', "/company/jobs/{$this->job}/hiring-manager-boundary", [], $this->recruiterToken)->assertStatus(422)->assertJsonValidationErrors(['stage_id']);
        $this->api('PUT', "/company/jobs/{$this->job}/hiring-manager-boundary", ['stage_id' => $this->stage('offer')], $this->managerToken)->assertForbidden()->assertJsonPath('permission', 'jobs.create');
        $this->assertNull(DB::table('job_jobs')->where('id', $this->job)->value('hiring_manager_from_stage_id'));
    }

    public function test_a_hiring_manager_on_an_interview_panel_sees_the_application(): void
    {
        $this->api('PUT', "/company/jobs/{$this->job}/hiring-manager-boundary", ['stage_id' => $this->stage('offer')], $this->recruiterToken)->assertNoContent();
        $this->moveToInterview();
        $this->assertNull($this->marker());

        $this->api('PUT', "/company/applications/{$this->applicationId}/interview", $this->interviewSchedule(['participant_ids' => [$this->managerId]]), $this->recruiterToken)->assertOk();

        $this->assertNotNull($this->marker());
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertOk();
    }

    public function test_reordering_the_stages_shows_what_now_qualifies(): void
    {
        $this->move('screening');
        $this->assertNull($this->marker());

        $order = DB::table('recruitment_stages')->where('company_id', $this->companyId)->orderBy('position')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $screening = $this->stage('screening');
        $interview = $this->stage('interview');
        $swapped = array_map(fn (int $id): int => $id === $screening ? $interview : ($id === $interview ? $screening : $id), $order);

        $this->api('PUT', '/company/stages/order', ['stage_ids' => $swapped], $this->adminToken)->assertOk();

        $this->assertNotNull($this->marker());
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertOk();
    }

    public function test_a_member_who_sees_every_job_is_not_bound_even_as_a_hiring_manager(): void
    {
        DB::table('job_assignments')->where('job_id', $this->job)->where('user_id', $this->recruiterId)->update(['role' => 'hiring_manager']);

        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->adminToken)->assertOk();
        $this->assertCount(1, $this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->adminToken)->json('data'));

        // The recruiter holds no jobs.view_all, so as a hiring manager they are bound.
        $this->assertSame([], $this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->recruiterToken)->json('data'));
    }

    public function test_a_hiring_manager_is_not_told_of_a_new_application_but_the_recruiter_is(): void
    {
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->recruiterId)->where('type', 'application.received')->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->managerId)->where('type', 'application.received')->count());
    }

    public function test_the_dashboard_and_the_tasks_of_a_hiring_manager_count_only_what_they_see(): void
    {
        $cards = $this->api('GET', '/company/dashboard', token: $this->managerToken)->assertOk()->json('data.cards');
        $this->assertSame(0, $cards['applications']['new']);
        $this->assertSame(1, $this->api('GET', '/company/dashboard', token: $this->recruiterToken)->json('data.cards.applications.new'));

        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['stage_entered_at' => now()->subDays(30)]);
        $stale = fn (string $token): int => (int) collect($this->api('GET', '/company/tasks', token: $token)->assertOk()->json('data.items'))->firstWhere('key', 'stale_applications')['count'];
        $this->assertSame(1, $stale($this->recruiterToken));
        $this->assertSame(0, $stale($this->managerToken));
    }

    public function test_the_other_applications_of_a_person_on_a_job_not_yet_visible_are_hidden(): void
    {
        $other = $this->insertJob('Data Analyst', 'active', [$this->recruiterId, $this->managerId], attributes: ['deadline' => '2026-12-31']);
        DB::table('job_assignments')->where('job_id', $other)->where('user_id', $this->managerId)->update(['role' => 'hiring_manager']);
        $otherApplication = (int) $this->apply($other)->assertCreated()->json('data.id');
        $this->moveToInterview();

        $detail = $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertOk();
        $detail->assertJsonPath('data.others_hidden', 1)->assertJsonCount(0, 'data.other_applications');

        DB::table('recruitment_applications')->where('id', $otherApplication)->update(['hiring_manager_visible_at' => now()]);
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->managerToken)->assertOk()->assertJsonPath('data.others_hidden', 0)->assertJsonCount(1, 'data.other_applications');
    }

    public function test_the_marker_keeps_the_time_of_the_first_qualifying_stage(): void
    {
        $this->assertNull($this->marker());
        $this->moveToInterview();
        $first = $this->marker();

        $this->travel(2)->hours();
        $this->move('offer', ['offer' => $this->offerDetails()]);

        $this->assertSame($first, $this->marker());
    }
}
