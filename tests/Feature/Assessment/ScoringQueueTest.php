<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-recruiter-workday.md` (the `my_scoring` task) and `docs/design/frontend.md` 8.5: `GET company/assessment/invitations?filter[my_scoring]=pending` for page C31. */
final class ScoringQueueTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    private int $testId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
        $this->testId = (int) DB::table('assessment_tests')->insertGetId([
            'kind' => 'skill', 'company_id' => $this->companyId, 'title' => 'Case study', 'measured_capabilities' => json_encode([]), 'status' => 'published', 'created_by_user_id' => $this->adminId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /**
     * @param  list<int>  $assessors
     */
    private function submitted(array $assessors, string $submittedAt, string $result = 'pending_scoring', string $status = 'submitted'): int
    {
        $invitation = (int) DB::table('assessment_invitations')->insertGetId([
            'application_id' => $this->applicationId, 'company_id' => $this->companyId, 'job_id' => $this->job, 'applicant_user_id' => $this->seekerId, 'test_id' => $this->testId,
            'invited_by_user_id' => $this->adminId, 'starts_at' => now()->subDays(3), 'due_at' => now()->addDays(3), 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($assessors as $userId) {
            DB::table('assessment_invitation_assessors')->insert(['invitation_id' => $invitation, 'user_id' => $userId]);
        }

        $attempt = (int) DB::table('assessment_attempts')->insertGetId([
            'invitation_id' => $invitation, 'consented_at' => now(), 'started_at' => now(), 'section_opened_at' => now(), 'ends_at' => now(), 'submitted_at' => $submittedAt, 'seed' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('assessment_results')->insert([
            'attempt_id' => $attempt, 'status' => $result, 'section_scores' => json_encode([]), 'integrity_tier' => 'none', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $invitation;
    }

    private function queue(?string $token = null, string $query = 'filter[my_scoring]=pending'): TestResponse
    {
        return $this->api('GET', '/company/assessment/invitations?'.$query, token: $token ?? $this->recruiterToken);
    }

    public function test_the_member_sees_the_submitted_tests_they_assess_with_the_longest_waiting_first(): void
    {
        $newer = $this->submitted([$this->recruiterId, $this->adminId], '2026-10-14 10:00:00');
        $older = $this->submitted([$this->recruiterId], '2026-10-12 08:00:00');

        $response = $this->queue()->assertOk();

        $this->assertSame([$older, $newer], array_column($response->json('data'), 'id'));
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertEquals(
            ['id' => $older, 'application_id' => $this->applicationId, 'status' => 'submitted', 'submitted_at' => '2026-10-12T08:00:00+00:00', 'candidate' => ['id' => $this->seekerId, 'name' => 'Siti Rahma'], 'job' => ['id' => $this->job, 'title' => 'Backend Developer']],
            array_intersect_key($response->json('data.0'), array_flip(['id', 'application_id', 'status', 'submitted_at', 'candidate', 'job'])),
        );
        $this->assertSame('Case study', $response->json('data.0.test.title'));
        $this->assertSame([$this->recruiterId], array_column($response->json('data.0.assessors'), 'id'));
        $this->assertArrayNotHasKey('score', $response->json('data.0'));
    }

    public function test_the_list_is_the_set_the_task_counts(): void
    {
        $this->submitted([$this->recruiterId], '2026-10-12 08:00:00');
        $this->submitted([$this->recruiterId], '2026-10-12 09:00:00', 'scored');
        $this->submitted([$this->recruiterId], '2026-10-12 10:00:00', 'pending_scoring', 'cancelled');
        $this->submitted([$this->adminId], '2026-10-12 11:00:00');

        $this->assertCount(1, $this->queue()->assertOk()->json('data'));
        $this->assertCount(1, $this->queue($this->adminToken)->assertOk()->json('data'));

        $tasks = collect($this->api('GET', '/company/tasks', token: $this->recruiterToken)->assertOk()->json('data.items'))->firstWhere('key', 'my_scoring');
        $this->assertSame(1, $tasks['count']);
        $this->assertSame('company/assessment/invitations?filter[my_scoring]=pending', $tasks['filter']);
    }

    public function test_the_list_is_paginated_with_a_total(): void
    {
        foreach (range(1, 3) as $hour) {
            $this->submitted([$this->recruiterId], "2026-10-12 0{$hour}:00:00");
        }

        $response = $this->queue(query: 'filter[my_scoring]=pending&per_page=2&page=2')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.current_page'));
        $this->assertSame(2, $response->json('meta.per_page'));
    }

    public function test_a_member_of_another_company_sees_nothing(): void
    {
        $this->submitted([$this->recruiterId], '2026-10-12 08:00:00');

        $this->assertSame([], $this->queue($this->otherAdminToken)->assertOk()->json('data'));
    }

    public function test_the_filter_is_required_and_only_pending_is_known(): void
    {
        $this->queue(query: '')->assertUnprocessable();
        $this->queue(query: 'filter[my_scoring]=done')->assertUnprocessable();
        $this->queue(query: 'filter[my_scoring]=pending&filter[status]=submitted')->assertUnprocessable();
        $this->queue(query: 'filter[my_scoring]=pending&per_page=0')->assertUnprocessable();
    }

    public function test_only_a_company_member_reads_it(): void
    {
        $this->queue($this->seekerToken)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken('')->getJson('/api/v1/company/assessment/invitations?filter[my_scoring]=pending')->assertUnauthorized();
    }
}
