<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Recruitment\Application\EraseApplicantData;
use Src\Recruitment\Application\SendReviewNotices;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-hiring-managers.md` section 3, part B: the notices and emails, the tasks, the answers on the application and the pipeline, and the erasure. */
final class ReviewNoticesTest extends FeatureTestCase
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

    private function moveClockTo(string $time): void
    {
        Carbon::setTestNow($time);
        Cache::flush();
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->managerToken = $this->tokenFor($this->managerId);
    }

    private function request(array $applications, string $due = '2026-10-08'): int
    {
        return (int) $this->api('POST', "/company/jobs/{$this->job}/review-requests", ['application_ids' => $applications, 'due_on' => $due, 'message' => 'Please look'], $this->recruiterToken)->assertCreated()->json('data.id');
    }

    private function answer(int $request, int $application, string $decision, ?string $reason = null): TestResponse
    {
        return $this->api('PUT', "/company/review-requests/{$request}/applications/{$application}/review", array_filter(['decision' => $decision, 'reason' => $reason]), $this->managerToken);
    }

    /** @return list<string> */
    private function types(int $userId, string $type): array
    {
        return DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', $type)->pluck('type')->all();
    }

    /** @return list<string> the subjects of the emails sent to the address */
    private function subjectsTo(string $address): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();
        $subjects = [];

        foreach ($transport->messages()->all() as $sent) {
            $email = $sent->getOriginalMessage();

            if (in_array($address, array_map(static fn ($to): string => $to->getAddress(), $email->getTo()), true)) {
                $subjects[] = (string) $email->getSubject();
            }
        }

        return $subjects;
    }

    /** @return array<string, int> */
    private function counts(string $token): array
    {
        Cache::flush();
        $items = $this->api('GET', '/company/tasks', token: $token)->assertOk()->json('data.items');

        return array_combine(array_column($items, 'key'), array_column($items, 'count'));
    }

    public function test_the_reviewers_are_told_in_app_and_by_email_when_a_review_is_requested(): void
    {
        $id = $this->request([$this->applicationId, $this->secondApplicationId]);

        $this->assertSame(['review.requested'], $this->types($this->managerId, 'review.requested'));
        $this->assertSame([], $this->types($this->recruiterId, 'review.requested'));
        $this->assertContains('Review requested for Backend Developer', $this->subjectsTo('manager@example.test'));
        $this->assertSame([], $this->subjectsTo('rina@example.test'));
        $this->assertSame($id, (int) json_decode((string) DB::table('notifications')->where('type', 'review.requested')->value('data'), true)['request_id']);
    }

    public function test_the_email_follows_the_preference_and_the_notice_stays(): void
    {
        $this->api('PUT', '/me/notification-preferences/team_reviews/email', ['enabled' => false], $this->managerToken)->assertNoContent();

        $this->request([$this->applicationId]);

        $this->assertSame(['review.requested'], $this->types($this->managerId, 'review.requested'));
        $this->assertSame([], $this->subjectsTo('manager@example.test'));
    }

    public function test_the_reminder_comes_the_day_before_at_eight_and_only_once(): void
    {
        $this->request([$this->applicationId, $this->secondApplicationId]);
        $notices = $this->app->make(SendReviewNotices::class);

        $this->moveClockTo('2026-10-07 00:30:00');
        $this->assertSame(['reminded' => 0, 'overdue' => 0], $notices->execute());

        $this->moveClockTo('2026-10-07 01:05:00');
        $this->assertSame(['reminded' => 1, 'overdue' => 0], $notices->execute());
        $this->assertSame(['reminded' => 0, 'overdue' => 0], $notices->execute());

        $this->assertSame(['review.reminder'], $this->types($this->managerId, 'review.reminder'));
        $this->assertContains('Review for Backend Developer is due tomorrow', $this->subjectsTo('manager@example.test'));
    }

    public function test_a_reviewer_who_answered_everything_gets_no_reminder_and_a_late_request_gets_none(): void
    {
        $answered = $this->request([$this->applicationId]);
        $this->answer($answered, $this->applicationId, 'advance')->assertOk();

        $this->moveClockTo('2026-10-07 09:00:00');
        $this->request([$this->secondApplicationId], '2026-10-08');

        $this->moveClockTo('2026-10-07 10:00:00');
        $this->assertSame(['reminded' => 0, 'overdue' => 0], $this->app->make(SendReviewNotices::class)->execute());
        $this->assertSame([], $this->types($this->managerId, 'review.reminder'));
    }

    public function test_the_requester_is_told_once_after_the_due_day(): void
    {
        $id = $this->request([$this->applicationId]);
        $notices = $this->app->make(SendReviewNotices::class);

        $this->moveClockTo('2026-10-08 16:00:00');
        $this->assertSame(0, $notices->execute()['overdue'], 'The due day in Jakarta time ends at 17:00 UTC.');

        $this->moveClockTo('2026-10-08 17:05:00');
        $this->assertSame(1, $notices->execute()['overdue']);
        $this->assertSame(0, $notices->execute()['overdue']);

        $this->assertSame(['review.overdue'], $this->types($this->recruiterId, 'review.overdue'));
        $this->assertSame(1, (int) json_decode((string) DB::table('notifications')->where('type', 'review.overdue')->value('data'), true)['pending']);
        $this->api('GET', '/company/review-requests?filter[role]=requester&filter[state]=overdue', token: $this->recruiterToken)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.overdue', true);
    }

    public function test_a_closed_or_complete_request_is_never_overdue(): void
    {
        $complete = $this->request([$this->applicationId]);
        $this->answer($complete, $this->applicationId, 'advance')->assertOk();
        $closed = $this->request([$this->secondApplicationId]);
        $this->api('POST', "/company/review-requests/{$closed}/close", [], $this->recruiterToken)->assertNoContent();

        $this->moveClockTo('2026-10-09 05:00:00');

        $this->assertSame(['reminded' => 0, 'overdue' => 0], $this->app->make(SendReviewNotices::class)->execute());
        $this->assertSame(0, $this->counts($this->recruiterToken)['reviews_overdue']);
    }

    public function test_the_requester_is_told_when_every_answer_is_in(): void
    {
        $id = $this->request([$this->applicationId, $this->secondApplicationId]);

        $this->answer($id, $this->applicationId, 'advance')->assertOk();
        $this->assertSame([], $this->types($this->recruiterId, 'review.completed'));

        $this->answer($id, $this->secondApplicationId, 'maybe')->assertOk();
        $this->answer($id, $this->secondApplicationId, 'advance')->assertOk();

        $this->assertSame(['review.completed'], $this->types($this->recruiterId, 'review.completed'));
    }

    public function test_the_tasks_count_the_unanswered_applications_and_the_overdue_requests(): void
    {
        $id = $this->request([$this->applicationId, $this->secondApplicationId]);

        $this->assertSame(2, $this->counts($this->managerToken)['my_reviews']);
        $this->assertSame(0, $this->counts($this->recruiterToken)['my_reviews']);
        $this->assertSame(0, $this->counts($this->recruiterToken)['reviews_overdue']);

        $this->answer($id, $this->applicationId, 'advance')->assertOk();
        $this->assertSame(1, $this->counts($this->managerToken)['my_reviews']);

        $this->moveClockTo('2026-10-09 05:00:00');
        $this->assertSame(1, $this->counts($this->recruiterToken)['reviews_overdue']);
        $this->assertSame(0, $this->counts($this->managerToken)['reviews_overdue'] ?? 0);

        $this->api('POST', "/company/review-requests/{$id}/close", [], $this->recruiterToken)->assertNoContent();
        $this->assertSame(0, $this->counts($this->recruiterToken)['reviews_overdue']);
        $this->assertSame(0, $this->counts($this->managerToken)['my_reviews']);
    }

    public function test_the_application_shows_the_answers_under_the_lock(): void
    {
        $second = $this->memberWith(['applications.view', 'applications.review'], 'second.manager@example.test');
        DB::table('job_assignments')->insert(['job_id' => $this->job, 'user_id' => $second['id'], 'role' => 'hiring_manager', 'created_at' => now()]);
        $id = $this->request([$this->applicationId]);
        $this->answer($id, $this->applicationId, 'decline', 'Too junior')->assertOk();

        $this->assertSame([], $this->api('GET', "/company/applications/{$this->applicationId}", token: $second['token'])->assertOk()->json('data.reviews'));

        $seen = $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->recruiterToken)->assertOk()->json('data.reviews');
        $this->assertCount(1, $seen);
        $this->assertSame('decline', $seen[0]['decision']);
        $this->assertSame('Too junior', $seen[0]['reason']);
        $this->assertSame($id, $seen[0]['request']['id']);
        $this->assertSame($this->managerId, $seen[0]['reviewer']['id']);

        $this->api('PUT', "/company/review-requests/{$id}/applications/{$this->applicationId}/review", ['decision' => 'advance'], $second['token'])->assertOk();
        $this->assertCount(2, $this->api('GET', "/company/applications/{$this->applicationId}", token: $second['token'])->assertOk()->json('data.reviews'));
    }

    public function test_the_pipeline_rows_carry_the_summary_of_the_reviews(): void
    {
        $id = $this->request([$this->applicationId, $this->secondApplicationId]);
        $this->answer($id, $this->applicationId, 'advance')->assertOk();

        $rows = collect($this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->recruiterToken)->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(['advance' => 1, 'decline' => 0, 'maybe' => 0, 'pending' => 0], $rows[$this->applicationId]['review_summary']);
        $this->assertSame(['advance' => 0, 'decline' => 0, 'maybe' => 0, 'pending' => 1], $rows[$this->secondApplicationId]['review_summary']);

        $locked = collect($this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->managerToken)->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(['advance' => 1, 'decline' => 0, 'maybe' => 0, 'pending' => 0], $locked[$this->applicationId]['review_summary']);
        $this->assertSame(['advance' => 0, 'decline' => 0, 'maybe' => 0, 'pending' => 1], $locked[$this->secondApplicationId]['review_summary']);
    }

    public function test_an_application_in_no_review_has_no_summary(): void
    {
        $rows = $this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->recruiterToken)->assertOk();

        $rows->assertJsonPath('data.0.review_summary', null);
    }

    public function test_erasing_the_applicant_clears_the_reasons_and_keeps_the_decisions(): void
    {
        $id = $this->request([$this->applicationId]);
        $this->answer($id, $this->applicationId, 'decline', 'Siti seemed unsure')->assertOk();

        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);

        $this->assertNull(DB::table('recruitment_application_reviews')->value('reason'));
        $this->assertSame('decline', DB::table('recruitment_application_reviews')->value('decision'));
    }

    public function test_the_command_runs_every_hour(): void
    {
        $this->artisan('recruitment:send-review-notices')->expectsOutput('Reminded 0 review requests, told the requester of 0 overdue ones.')->assertSuccessful();

        $event = collect($this->app->make(Schedule::class)->events())->first(static fn ($event): bool => str_contains((string) $event->command, 'recruitment:send-review-notices'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }
}
