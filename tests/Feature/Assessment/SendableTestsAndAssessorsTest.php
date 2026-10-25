<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/** The two lists the "send a test" dialog reads: the tests a member may send and the members who may score a test on a job. */
final class SendableTestsAndAssessorsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function skill(string $title, int $seconds = 600, string $area = 'company', bool $publish = true): int
    {
        $id = $this->newTest($area, ['title' => $title]);
        $section = (int) $this->{$area}('POST', "tests/{$id}/sections", $this->section('Basics', $seconds))->assertCreated()->json('data.id');
        $this->{$area}('POST', "sections/{$section}/questions", $this->choice())->assertCreated();

        if ($publish) {
            $this->{$area}('POST', "tests/{$id}/publish")->assertOk();
        }

        return $id;
    }

    private function battery(string $title = 'Personality'): int
    {
        $id = $this->newTest('provider', ['title' => $title]);
        $section = (int) $this->provider('POST', "tests/{$id}/sections", $this->section('Statements', 300))->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$section}/questions", [
            'type' => 'likert', 'text' => 'I enjoy meeting new people.', 'scale_key' => 'extraversion',
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Sometimes', 'score_value' => 2], ['label' => 'Always', 'score_value' => 3]],
        ])->assertCreated();
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [['scale_key' => 'extraversion', 'raw_min' => 0, 'raw_max' => 10, 'standard_score' => 50, 'band' => 'Average']]])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Dear {{candidate_name}}'])->assertOk();
        $this->provider('POST', "tests/{$id}/publish")->assertOk();

        return $id;
    }

    private function sendable(string $query = '', ?string $token = null): TestResponse
    {
        return $this->api('GET', '/company/assessment/sendable-tests'.($query === '' ? '' : '?'.$query), [], $token ?? $this->recruiterToken);
    }

    /** @return list<int> */
    private function assessors(?string $token = null, ?int $job = null): array
    {
        return array_column($this->api('GET', '/company/jobs/'.($job ?? $this->job).'/assessors', [], $token ?? $this->recruiterToken)->assertOk()->json('data'), 'id');
    }

    // ----- the tests -----

    public function test_a_member_who_sends_tests_reads_the_own_library_and_battery_tests_in_one_list(): void
    {
        $mine = $this->skill('Logic and numbers', 900);
        $this->skill('Unpublished draft', publish: false);
        $archived = $this->skill('Old test');
        $this->company('POST', "tests/{$archived}/archive")->assertOk();
        $theirs = (int) $this->company('POST', 'tests', ['title' => 'Theirs', 'measured_capabilities' => ['Logic']], $this->otherAdminToken)->assertCreated()->json('data.id');
        $library = $this->skill('Library basics', 300, 'library');
        $battery = $this->battery();
        $notGranted = $this->battery('Not granted');
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $battery])->assertNoContent();
        $this->overrideSetting('assessment.psychological_enabled', true);

        $rows = $this->sendable()->assertOk()->json('data');

        $this->assertSame(['id', 'title', 'kind', 'source', 'description', 'section_count', 'total_seconds'], array_keys($rows[0]));
        $this->assertSame(
            [[$mine, 'company', 'skill', 1, 900], [$library, 'library', 'skill', 1, 300], [$battery, 'battery', 'psychological', 1, 300]],
            array_map(fn (array $row): array => [$row['id'], $row['source'], $row['kind'], $row['section_count'], $row['total_seconds']], $rows),
        );
        $this->assertNotContains($theirs, array_column($rows, 'id'));
        $this->assertNotContains($notGranted, array_column($rows, 'id'));
    }

    public function test_batteries_are_left_out_while_the_company_may_not_use_them(): void
    {
        $battery = $this->battery();
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $battery])->assertNoContent();
        $mine = $this->skill('Logic and numbers');

        $this->assertSame([$mine], array_column($this->sendable()->assertOk()->json('data'), 'id'));
    }

    public function test_the_list_is_narrowed_by_title_and_each_source_is_in_title_order(): void
    {
        $b = $this->skill('Beta test');
        $a = $this->skill('alpha test');
        $library = $this->skill('Beta library', 300, 'library');

        $this->assertSame([$a, $b, $library], array_column($this->sendable()->assertOk()->json('data'), 'id'));
        $this->assertSame([$b, $library], array_column($this->sendable('filter[q]=beta')->assertOk()->json('data'), 'id'));
        $this->sendable('filter[other]=1')->assertStatus(422);
    }

    public function test_every_test_in_the_list_can_be_sent(): void
    {
        $this->skill('Logic and numbers');
        $this->skill('Library basics', 300, 'library');
        $battery = $this->battery();
        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $battery])->assertNoContent();
        $this->overrideSetting('assessment.psychological_enabled', true);
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');
        $ids = array_column($this->sendable()->assertOk()->json('data'), 'id');
        $this->assertCount(3, $ids);

        $due = now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'assessment' => ['test_id' => $ids[0], 'due_at' => $due]], $this->recruiterToken)->assertOk();

        foreach ($ids as $id) {
            $this->company('PUT', "applications/{$this->applicationId}/invitation", ['test_id' => $id, 'due_at' => $due], $this->recruiterToken)->assertSuccessful();
        }
    }

    public function test_only_a_member_who_may_send_tests_reads_the_list(): void
    {
        $author = $this->memberWith(['assessments.author'], 'author@example.test');

        $this->sendable(token: $author['token'])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->sendable(token: $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->sendable(token: $this->adminToken)->assertOk();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/company/assessment/sendable-tests')->assertUnauthorized();
    }

    // ----- the assessors -----

    public function test_the_assessors_are_the_active_members_who_may_see_the_job_company_admins_included(): void
    {
        $viewer = $this->memberWith(['jobs.view_all'], 'viewer@example.test');
        $suspended = $this->memberWith(['jobs.view_all'], 'gone@example.test');
        DB::table('identity_users')->where('id', $suspended['id'])->update(['status' => 'suspended']);

        $response = $this->api('GET', "/company/jobs/{$this->job}/assessors", [], $this->recruiterToken)->assertOk();

        $this->assertSame(['id', 'name'], array_keys($response->json('data.0')));
        $this->assertSame([$this->adminId, $viewer['id'], $this->recruiterId], array_column($response->json('data'), 'id'));
        $this->assertSame(['Andi Admin', 'Limited Member', 'Rina Rekruter'], array_column($response->json('data'), 'name'));
        $this->assertNotContains($this->secondRecruiterId, array_column($response->json('data'), 'id'));
        $this->assertNotContains($this->otherAdminId, array_column($response->json('data'), 'id'));
    }

    public function test_every_assessor_in_the_list_is_accepted_and_the_others_are_not(): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');
        $test = $this->skill('Logic and numbers');
        $due = now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'assessment' => ['test_id' => $test, 'due_at' => $due]], $this->recruiterToken)->assertOk();

        $this->company('PUT', "applications/{$this->applicationId}/invitation", ['test_id' => $test, 'due_at' => $due, 'assessor_ids' => $this->assessors()], $this->recruiterToken)->assertSuccessful();
        $this->company('PUT', "applications/{$this->applicationId}/invitation", ['test_id' => $test, 'due_at' => $due, 'assessor_ids' => [$this->secondRecruiterId]], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-assessor');
    }

    public function test_only_a_member_who_sends_tests_and_sees_the_job_reads_the_assessors(): void
    {
        $this->api('GET', "/company/jobs/{$this->job}/assessors", [], $this->secondRecruiterToken)->assertNotFound();
        $this->api('GET', "/company/jobs/{$this->job}/assessors", [], $this->otherAdminToken)->assertNotFound();
        $this->api('GET', '/company/jobs/999999/assessors', [], $this->adminToken)->assertNotFound();
        $this->api('GET', "/company/jobs/{$this->job}/assessors", [], $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');

        $viewer = $this->memberWith(['jobs.view_all'], 'viewer@example.test');
        $this->api('GET', "/company/jobs/{$this->job}/assessors", [], $viewer['token'])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required');

        $this->assertSame([$this->adminId, $viewer['id'], $this->recruiterId], $this->assessors($this->adminToken));
    }
}
