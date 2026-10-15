<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Src\Identity\Domain\Events\UserAnonymized;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** One application from the first click to the erasure of the applicant: the two sides see consistent things at every step. */
final class PipelineJourneyTest extends FeatureTestCase
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

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    public function test_an_application_goes_from_apply_to_rejection_to_erasure_and_both_sides_agree_at_every_step(): void
    {
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $relocate = $this->question('yes_no', 'Can you relocate?');
        $this->setQuestions($job, [['question_id' => $relocate, 'required' => true, 'must_have' => true, 'ideal' => ['yes' => true]]]);

        // 1. The job seeker applies; the assigned recruiter is told.
        $id = (int) $this->apply($job, ['cover_letter' => 'Hello', 'answers' => [['question_id' => $relocate, 'value' => true]]])->assertCreated()->json('data.id');
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->recruiterId)->where('type', 'application.received')->count());

        // 2. The recruiter finds it in the pipeline, with the must-have met, in the first column.
        $list = $this->api('GET', "/company/jobs/{$job}/applications", token: $this->recruiterToken)->assertOk();
        $this->assertSame([$id], array_column($list->json('data'), 'id'));
        $this->assertTrue($list->json('data.0.meets_must_have'));
        $this->assertSame([1, 0, 0, 0, 0, 0, 0, 0], array_column($list->json('stages'), 'count'));

        // 3. The recruiter moves it to the interview: the applicant is told and sees the status, not the company's stage names.
        $this->api('POST', "/company/applications/{$id}/move", ['stage_id' => $this->stage('interview'), 'interview' => $this->interviewSchedule()], $this->recruiterToken)->assertNoContent();
        $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken)->assertOk()->assertJsonPath('data.status', 'interview');
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->seekerId)->where('type', 'application.moved_to_interview')->count());
        $this->assertSame([0, 0, 0, 1, 0, 0, 0, 0], array_column($this->api('GET', "/company/jobs/{$job}/applications", token: $this->recruiterToken)->json('stages'), 'count'));

        // 4. The recruiter rejects it with a reason; the applicant sees the status and the reason code, not the note.
        $this->api('POST', "/company/applications/{$id}/reject", ['reason' => 'failed_screening', 'note' => 'Weak answers'], $this->recruiterToken)->assertNoContent();
        $mine = $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken)->assertOk();
        $mine->assertJsonPath('data.status', 'rejected');
        $mine->assertJsonPath('data.rejection_reason', 'failed_screening');
        $this->assertStringNotContainsString('Weak answers', (string) json_encode($mine->json()));
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->seekerId)->where('type', 'application.rejected')->count());

        // 5. The application is final: the applicant can neither withdraw nor apply again, and the recruiter cannot move it.
        $this->api('POST', "/me/applications/{$id}/withdraw", token: $this->seekerToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-not-withdrawable');
        $this->apply($job)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-applied');
        $this->api('POST', "/company/applications/{$id}/move", ['stage_id' => $this->stage('offer')], $this->recruiterToken)->assertStatus(409);

        // 6. The recruiter's history tells the whole story.
        $history = $this->api('GET', "/company/applications/{$id}", token: $this->recruiterToken)->assertOk()->json('data.history');
        $this->assertSame(['applied', 'interview', 'rejected'], array_map(fn (array $entry): string => $entry['to']['type'], $history));
        $this->assertSame(['applicant', 'recruiter', 'recruiter'], array_column($history, 'by'));
        $this->assertSame([null, null, 'failed_screening'], array_column($history, 'reason'));
        $this->assertSame('Weak answers', $history[2]['note']);

        // 7. The account is anonymised: the application stays as a statistic without the person.
        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        $detail = $this->api('GET', "/company/applications/{$id}", token: $this->recruiterToken)->assertOk();
        $detail->assertJsonPath('data.applicant', ['is_deleted' => true, 'name' => null])->assertJsonPath('data.cover_letter', null)->assertJsonPath('data.resume', null)
            ->assertJsonPath('data.stage.type', 'rejected')->assertJsonPath('data.meets_must_have', true)->assertJsonCount(3, 'data.history');
        $this->assertSame([0, 0, 0, 0, 0, 0, 1, 0], array_column($this->api('GET', "/company/jobs/{$job}/applications", token: $this->recruiterToken)->json('stages'), 'count'));
        $this->api('GET', "/company/applications/{$id}/resume", token: $this->recruiterToken)->assertStatus(404);
    }
}
