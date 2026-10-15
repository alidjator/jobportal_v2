<?php

namespace Tests\Feature\Company;

use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

/** What each permission of a company member lets them do in the other contexts (`docs/design/phase-8-company-permissions.md`). */
final class CompanyMemberPermissionsTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    private const string NEEDED = 'urn:onesis:error:company-permission-required';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    public function test_a_member_without_permissions_reaches_no_job_or_applicant(): void
    {
        $id = $this->insertJob('Assigned', 'active', [$this->adminId]);
        $none = $this->memberWith(['company.profile'], 'none@example.test')['token'];

        $this->api('GET', '/company/jobs', token: $none)->assertForbidden()->assertJsonPath('type', self::NEEDED)->assertJsonPath('permission', 'applications.view');
        $this->api('GET', "/company/jobs/{$id}", token: $none)->assertForbidden()->assertJsonPath('type', self::NEEDED);
        $this->api('GET', "/company/jobs/{$id}/applications", token: $none)->assertForbidden()->assertJsonPath('type', self::NEEDED);

        $cards = $this->api('GET', '/company/dashboard', token: $none)->assertOk()->json('data.cards');
        $this->assertArrayNotHasKey('jobs', $cards);
        $this->assertArrayNotHasKey('applications', $cards);
    }

    public function test_reading_is_not_enough_to_change_a_job(): void
    {
        $viewer = $this->memberWith(['applications.view'], 'viewer@example.test');
        $id = $this->insertJob('Assigned', 'draft', [$viewer['id']]);

        $this->api('GET', "/company/jobs/{$id}", token: $viewer['token'])->assertOk();
        $this->api('PATCH', "/company/jobs/{$id}", ['title' => 'Changed'], $viewer['token'])->assertForbidden()->assertJsonPath('type', self::NEEDED)->assertJsonPath('permission', 'jobs.create');
        $this->api('POST', "/company/jobs/{$id}/publish", token: $viewer['token'])->assertForbidden()->assertJsonPath('permission', 'jobs.publish');
        $this->api('PUT', "/company/jobs/{$id}/assignees", ['user_ids' => [$viewer['id']]], $viewer['token'])->assertForbidden()->assertJsonPath('permission', 'jobs.assign');
    }

    public function test_only_view_all_widens_the_scope_beyond_the_assigned_jobs(): void
    {
        $viewer = $this->memberWith(['applications.view'], 'viewer@example.test');
        $everything = $this->memberWith(['applications.view', 'jobs.view_all'], 'everything@example.test');
        $id = $this->insertJob('Not theirs', 'active', [$this->adminId]);

        $this->api('GET', "/company/jobs/{$id}", token: $viewer['token'])->assertNotFound();
        $this->api('GET', "/company/jobs/{$id}", token: $everything['token'])->assertOk();
        $this->api('GET', '/company/jobs', token: $viewer['token'])->assertOk()->assertJsonCount(0, 'data');
        $this->assertContains($id, array_column($this->api('GET', '/company/jobs', token: $everything['token'])->assertOk()->json('data'), 'id'));
    }

    public function test_the_library_permissions_are_separate(): void
    {
        $recruiter = $this->recruiterToken;

        $this->api('POST', '/company/stages', ['name' => 'Panel', 'type' => 'interview'], $recruiter)->assertForbidden()->assertJsonPath('permission', 'stages.manage');
        $this->api('POST', '/company/screening-questions', ['type' => 'yes_no', 'text' => 'Do you drive a car?'], $recruiter)->assertForbidden()->assertJsonPath('permission', 'screening_questions.manage');
        $this->api('GET', '/company/screening-questions', token: $recruiter)->assertOk();
        $this->api('GET', '/company/campaigns', token: $recruiter)->assertForbidden()->assertJsonPath('permission', 'campaigns.manage');
        $this->api('GET', '/company/profile', token: $recruiter)->assertOk();
        $this->api('PATCH', '/company/profile', ['bio' => 'x'], $recruiter)->assertForbidden()->assertJsonPath('permission', 'company.profile');

        $librarian = $this->memberWith(['stages.manage', 'screening_questions.manage', 'campaigns.manage', 'company.profile'], 'library@example.test')['token'];

        $this->api('POST', '/company/stages', ['name' => 'Panel', 'type' => 'interview'], $librarian)->assertCreated();
        $this->api('GET', '/company/campaigns', token: $librarian)->assertOk();
        $this->api('GET', '/company/screening-questions', token: $librarian)->assertOk();
    }

    public function test_the_interviewer_preset_cannot_move_reject_or_export(): void
    {
        $interviewer = $this->memberWith(['applications.view', 'interviews.manage'], 'interviewer@example.test');
        $id = $this->insertJob('Assigned', 'active', [$interviewer['id']]);

        $this->api('GET', "/company/jobs/{$id}/applications", token: $interviewer['token'])->assertOk();
        $this->api('GET', "/company/jobs/{$id}/applications/export", token: $interviewer['token'])->assertForbidden()->assertJsonPath('permission', 'applications.export');
        $this->api('POST', "/company/jobs/{$id}/applications/bulk-reject", ['application_ids' => [1], 'reason' => 'position_filled'], $interviewer['token'])->assertForbidden()->assertJsonPath('permission', 'applications.manage');
    }

    public function test_the_talent_pool_and_candidate_search_need_their_permissions(): void
    {
        $viewer = $this->memberWith(['applications.view'], 'viewer@example.test')['token'];

        $this->api('GET', '/company/talent-pool', token: $viewer)->assertForbidden()->assertJsonPath('permission', 'talent_pool.manage');
        $this->api('GET', '/company/candidates', token: $viewer)->assertForbidden()->assertJsonPath('permission', 'candidates.search');
        $this->api('GET', '/company/talent-pool', token: $this->recruiterToken)->assertOk();
    }
}
