<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-hiring-managers.md` section 3: shortlist reviews (requests, answers, the lock, closing). */
final class ReviewRequestTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $managerId;

    private string $managerToken;

    private int $applicationId;

    private int $secondApplicationId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 08:00:00');
        $this->buildApplicationFixtures();
        $this->verifyTheCompany();

        $manager = $this->memberWith(['applications.view', 'applications.review'], 'manager@example.test');
        $this->managerId = $manager['id'];
        $this->managerToken = $manager['token'];

        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId, $this->managerId], attributes: ['deadline' => '2026-12-31']);
        DB::table('job_assignments')->where('job_id', $this->job)->where('user_id', $this->managerId)->update(['role' => 'hiring_manager']);

        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
        $token = $this->tokenFor($this->seeker('budi.pelamar@example.test', name: 'Budi Pelamar'));
        $this->secondApplicationId = (int) $this->apply($this->job, ['resume_id' => $this->completeProfile($token)], $token)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @param  list<int>|null  $reviewers */
    private function request(array $applications, ?array $reviewers = null, string $due = '2026-10-08', ?string $token = null): TestResponse
    {
        return $this->api('POST', "/company/jobs/{$this->job}/review-requests", array_filter([
            'application_ids' => $applications, 'reviewer_ids' => $reviewers, 'due_on' => $due, 'message' => 'Please look at these two.',
        ], static fn (mixed $value): bool => $value !== null), $token ?? $this->recruiterToken);
    }

    private function answer(int $request, int $application, string $decision, ?string $reason = null, ?string $token = null): TestResponse
    {
        return $this->api('PUT', "/company/review-requests/{$request}/applications/{$application}/review", array_filter(['decision' => $decision, 'reason' => $reason]), $token ?? $this->managerToken);
    }

    private function secondManager(): array
    {
        $second = $this->memberWith(['applications.view', 'applications.review'], 'second.manager@example.test');
        DB::table('job_assignments')->insert(['job_id' => $this->job, 'user_id' => $second['id'], 'role' => 'hiring_manager', 'created_at' => now()]);

        return $second;
    }

    public function test_a_recruiter_asks_the_hiring_managers_and_the_applications_become_visible_to_them(): void
    {
        $this->assertNull(DB::table('recruitment_applications')->where('id', $this->applicationId)->value('hiring_manager_visible_at'));

        $response = $this->request([$this->applicationId, $this->secondApplicationId])->assertCreated();

        $response->assertJsonPath('data.status', 'open')->assertJsonPath('data.due_on', '2026-10-08')->assertJsonPath('data.message', 'Please look at these two.')
            ->assertJsonPath('data.reviewers.0.id', $this->managerId)->assertJsonPath('data.job.title', 'Backend Developer')
            ->assertJsonPath('data.progress', ['answered' => 0, 'total' => 2])->assertJsonPath('data.pending_for_me', null)
            ->assertJsonCount(2, 'data.items')->assertJsonPath('data.items.0.candidate_name', 'Siti Rahma')->assertJsonPath('data.items.0.card', null);

        $this->assertNotNull(DB::table('recruitment_applications')->where('id', $this->applicationId)->value('hiring_manager_visible_at'));
        $this->assertNotNull(DB::table('recruitment_applications')->where('id', $this->secondApplicationId)->value('hiring_manager_visible_at'));
    }

    public function test_the_request_is_checked(): void
    {
        $this->request([$this->applicationId], [$this->recruiterId])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-reviewers');
        $this->request([$this->applicationId], [])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-reviewers');
        $this->request([$this->applicationId], null, '2026-10-04')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-due-date');
        $this->request([$this->applicationId], null, '2026-11-05')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-due-date');
        $this->request([$this->applicationId], null, '2026-10-05')->assertCreated();
        $this->request([$this->applicationId], null, '2026-11-04')->assertCreated();
        $this->request([999999])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-applications');
        $this->request([])->assertStatus(422)->assertJsonValidationErrors(['application_ids']);
        $this->request([$this->applicationId], null, 'tomorrow')->assertStatus(422)->assertJsonValidationErrors(['due_on']);

        $this->api('POST', "/company/applications/{$this->secondApplicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();
        $this->request([$this->secondApplicationId])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-applications');

        $this->request([$this->applicationId], null, '2026-10-08', $this->managerToken)->assertForbidden();
    }

    public function test_a_job_without_a_hiring_manager_cannot_be_reviewed(): void
    {
        DB::table('job_assignments')->where('job_id', $this->job)->where('user_id', $this->managerId)->update(['role' => 'recruiter']);

        $this->request([$this->applicationId])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-reviewers');
    }

    public function test_the_reviewer_finds_the_request_in_their_list_and_answers(): void
    {
        $id = (int) $this->request([$this->applicationId, $this->secondApplicationId])->json('data.id');

        $this->api('GET', '/company/review-requests?filter[state]=pending', token: $this->managerToken)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.pending_for_me', 2);
        $this->api('GET', '/company/review-requests?filter[state]=answered', token: $this->managerToken)->assertOk()->assertJsonCount(0, 'data');

        $this->answer($id, $this->applicationId, 'advance')->assertOk()->assertJsonPath('data.pending_for_me', 1)->assertJsonPath('data.status', 'open');
        $this->answer($id, $this->secondApplicationId, 'decline')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:reason-required');
        $this->answer($id, $this->secondApplicationId, 'decline', 'Not enough experience')->assertOk()->assertJsonPath('data.status', 'complete')->assertJsonPath('data.pending_for_me', 0)
            ->assertJsonPath('data.progress', ['answered' => 2, 'total' => 2]);

        $this->assertSame('advance', DB::table('recruitment_application_reviews')->where('application_id', $this->applicationId)->value('decision'));
        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'), 'An answer never moves an application.');

        $this->api('GET', '/company/review-requests?filter[state]=answered', token: $this->managerToken)->assertOk()->assertJsonCount(1, 'data');
        $this->api('GET', '/company/review-requests?filter[state]=pending', token: $this->managerToken)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_answer_can_change_until_the_request_is_closed(): void
    {
        $id = (int) $this->request([$this->applicationId])->json('data.id');

        $this->answer($id, $this->applicationId, 'maybe')->assertOk();
        $this->answer($id, $this->applicationId, 'advance')->assertOk();
        $this->assertSame(1, DB::table('recruitment_application_reviews')->count());
        $this->assertSame('advance', DB::table('recruitment_application_reviews')->value('decision'));

        $this->api('POST', "/company/review-requests/{$id}/close", [], $this->recruiterToken)->assertNoContent();
        $this->api('POST', "/company/review-requests/{$id}/close", [], $this->recruiterToken)->assertNoContent();

        $this->answer($id, $this->applicationId, 'decline', 'Changed my mind')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:review-request-closed');
        $this->assertSame('closed', DB::table('recruitment_review_requests')->value('status'));
        $this->assertSame('advance', DB::table('recruitment_application_reviews')->value('decision'), 'The answers stay.');
    }

    public function test_a_reviewer_sees_the_others_only_after_sending_their_own(): void
    {
        $second = $this->secondManager();
        $id = (int) $this->request([$this->applicationId])->json('data.id');
        $this->answer($id, $this->applicationId, 'decline', 'Too junior')->assertOk();

        $before = $this->api('GET', "/company/review-requests/{$id}", token: $second['token'])->assertOk();
        $before->assertJsonPath('data.items.0.locked', true);
        $others = collect($before->json('data.items.0.answers'))->firstWhere('mine', false);
        $this->assertTrue($others['answered']);
        $this->assertNull($others['decision']);
        $this->assertNull($others['reason']);

        $this->answer($id, $this->applicationId, 'advance', null, $second['token'])->assertOk();

        $after = collect($this->api('GET', "/company/review-requests/{$id}", token: $second['token'])->assertOk()->json('data.items.0.answers'))->firstWhere('mine', false);
        $this->assertSame('decline', $after['decision']);
        $this->assertSame('Too junior', $after['reason']);

        $requester = collect($this->api('GET', "/company/jobs/{$this->job}/review-requests", token: $this->recruiterToken)->assertOk()->json('data.0.items.0.answers'));
        $this->assertSame(['advance', 'decline'], $requester->pluck('decision')->sort()->values()->all());
    }

    public function test_the_review_page_has_a_card_for_each_candidate(): void
    {
        $id = (int) $this->request([$this->applicationId])->json('data.id');

        $card = $this->api('GET', "/company/review-requests/{$id}", token: $this->managerToken)->assertOk()->json('data.items.0.card');

        $this->assertSame('Siti Rahma', $card['name']);
        $this->assertArrayHasKey('headline', $card);
        $this->assertArrayHasKey('latest_experience', $card);
        $this->assertArrayHasKey('education', $card);
        $this->assertArrayHasKey('expected_salary', $card);
        $this->assertArrayHasKey('screening_answers', $card);
        $this->assertArrayHasKey('interview_result', $card);
    }

    public function test_someone_outside_the_request_does_not_see_it(): void
    {
        $id = (int) $this->request([$this->applicationId])->json('data.id');
        $stranger = $this->memberWith(['applications.view', 'applications.review'], 'stranger@example.test');

        $this->api('GET', "/company/review-requests/{$id}", token: $stranger['token'])->assertNotFound();
        $this->answer($id, $this->applicationId, 'advance', null, $stranger['token'])->assertNotFound();
        $this->api('POST', "/company/review-requests/{$id}/close", [], $stranger['token'])->assertNotFound();
        $this->api('POST', "/company/review-requests/{$id}/close", [], $this->managerToken)->assertNotFound();
        $this->api('GET', "/company/review-requests/{$id}", token: $this->otherAdminToken)->assertNotFound();
        $this->api('GET', '/company/review-requests', token: $stranger['token'])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_application_that_became_final_needs_no_answer(): void
    {
        $id = (int) $this->request([$this->applicationId, $this->secondApplicationId])->json('data.id');
        $this->api('POST', "/company/applications/{$this->secondApplicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();

        $this->answer($id, $this->secondApplicationId, 'advance')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-final');
        $response = $this->answer($id, $this->applicationId, 'advance')->assertOk()->assertJsonPath('data.status', 'complete');

        $response->assertJsonPath('data.items.1.active', false)->assertJsonPath('data.progress', ['answered' => 1, 'total' => 1]);
        $this->answer($id, 999999, 'advance')->assertNotFound();
    }

    public function test_the_requests_of_a_job_are_listed_for_the_requester_only(): void
    {
        $first = (int) $this->request([$this->applicationId])->json('data.id');
        $second = (int) $this->request([$this->secondApplicationId])->json('data.id');

        $this->api('GET', "/company/jobs/{$this->job}/review-requests", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.0.id', $second)->assertJsonPath('data.1.id', $first)->assertJsonPath('meta.total', 2);
        $this->api('GET', "/company/jobs/{$this->job}/review-requests", token: $this->managerToken)->assertForbidden();
    }
}
