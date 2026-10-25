<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interviewers.md` section 1: the members who may be chosen for an interview panel, for whoever schedules interviews. */
final class InterviewersListTest extends FeatureTestCase
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

    /** @return list<int> */
    private function ids(?string $token = null): array
    {
        return array_column($this->api('GET', '/company/interviewers', [], $token)->assertOk()->json('data'), 'id');
    }

    public function test_it_lists_the_company_admins_and_the_members_who_may_take_part_by_name(): void
    {
        $interviewer = $this->memberWith(['interviews.participate'], 'panel@example.test');
        $this->memberWith(['applications.view'], 'viewer@example.test');
        $this->memberWith(['interviews.participate'], 'other@example.test', $this->otherCompanyId);

        $response = $this->api('GET', '/company/interviewers', [], $this->recruiterToken)->assertOk();

        $this->assertSame(['id', 'name'], array_keys($response->json('data.0')));
        $this->assertSame(
            [$this->adminId, $interviewer['id'], $this->recruiterId, $this->secondRecruiterId],
            array_column($response->json('data'), 'id'),
        );
        $this->assertSame(['Andi Admin', 'Limited Member', 'Rina Rekruter', 'Sari Rekruter'], array_column($response->json('data'), 'name'));
        $this->assertArrayNotHasKey('links', $response->json());
    }

    public function test_the_list_is_the_set_the_scheduler_accepts(): void
    {
        $interviewer = $this->memberWith(['interviews.participate'], 'panel@example.test');
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $application = (int) $this->apply($job)->assertCreated()->json('data.id');
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');

        $this->api('POST', "/company/applications/{$application}/move", [
            'stage_id' => $stage, 'interview' => $this->interviewSchedule(['interviewer_id' => $interviewer['id']]),
        ], $this->recruiterToken)->assertOk();
    }

    public function test_a_suspended_member_is_not_listed(): void
    {
        $interviewer = $this->memberWith(['interviews.participate'], 'panel@example.test');
        DB::table('identity_users')->where('id', $interviewer['id'])->update(['status' => 'suspended']);

        $this->assertNotContains($interviewer['id'], $this->ids());
    }

    public function test_only_a_member_who_schedules_interviews_reads_it(): void
    {
        $viewer = $this->memberWith(['applications.view', 'interviews.participate'], 'viewer@example.test');

        $this->api('GET', '/company/interviewers', [], $viewer['token'])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->api('GET', '/company/interviewers', [], $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');

        $manager = $this->memberWith(['interviews.manage'], 'manager@example.test');
        $this->assertSame(
            [$this->adminId, $viewer['id'], $this->recruiterId, $this->secondRecruiterId],
            $this->ids($manager['token']),
            'It reads the list without being on it; the viewer above is on it because that member may take part.',
        );

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/company/interviewers')->assertUnauthorized();
    }

    public function test_another_company_sees_only_its_own_people(): void
    {
        $this->assertSame([$this->otherAdminId], $this->ids($this->otherAdminToken));
    }
}
