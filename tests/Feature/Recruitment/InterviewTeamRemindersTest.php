<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Recruitment\Application\SendInterviewReminders;
use Src\Recruitment\Application\SendScorecardReminders;
use Src\Recruitment\Application\SendStartingSoonReminders;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interviewers.md` sections 3 and 4: the reminders to the whole panel and the scorecard follow-up at 24 and 72 hours. */
final class InterviewTeamRemindersTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 08:00:00');
        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** Schedules an online interview on the 8th, 09:00 to 10:00 Jakarta time, with the admin on the panel, and lets the job seeker confirm it. */
    private function openInterview(bool $confirm = true): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        $schedule = $this->interviewSchedule(['starts_at' => '2026-10-08T02:00:00Z', 'participant_ids' => [$this->adminId]]);
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $schedule], $this->recruiterToken)->assertOk();

        if ($confirm) {
            $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();
        }
    }

    private function moveClockTo(string $time): void
    {
        Carbon::setTestNow($time);
        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->seekerToken = $this->tokenFor($this->seekerId);
    }

    /** @return list<string> */
    private function types(int $userId, string $like): array
    {
        return DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', $like)->pluck('type')->all();
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

    /** @return list<string> */
    private function scorecardSubjectsTo(string $address): array
    {
        return array_values(array_filter($this->subjectsTo($address), static fn (string $subject): bool => str_contains($subject, 'scorecard')));
    }

    public function test_the_day_before_reminder_reaches_the_whole_panel_in_app_and_by_email(): void
    {
        $this->openInterview();
        $this->moveClockTo('2026-10-07 10:00:00');

        $this->assertSame(1, $this->app->make(SendInterviewReminders::class)->execute());

        foreach ([$this->recruiterId, $this->adminId] as $member) {
            $this->assertSame(['interview.reminder'], $this->types($member, 'interview.reminder'));
        }

        $this->assertSame([], $this->types($this->secondRecruiterId, 'interview.%'));
        $this->assertContains('Interview tomorrow for Backend Developer', $this->subjectsTo('andi@example.test'));
        $this->assertContains('Interview tomorrow for Backend Developer', $this->subjectsTo('rina@example.test'));
        $this->assertSame([], $this->subjectsTo('sari@example.test'));
    }

    public function test_the_day_before_email_follows_the_interview_preference(): void
    {
        $this->openInterview();
        $this->api('PUT', '/me/notification-preferences/team_interview_changes/email', ['enabled' => false], $this->adminToken)->assertNoContent();
        $this->moveClockTo('2026-10-07 10:00:00');

        $this->app->make(SendInterviewReminders::class)->execute();

        $this->assertSame(['interview.reminder'], $this->types($this->adminId, 'interview.reminder'));
        $this->assertNotContains('Interview tomorrow for Backend Developer', $this->subjectsTo('andi@example.test'));
    }

    public function test_thirty_minutes_before_the_start_the_panel_is_told_in_app_once(): void
    {
        $this->openInterview();
        $reminders = $this->app->make(SendStartingSoonReminders::class);

        $this->moveClockTo('2026-10-08 01:20:00');
        $this->assertSame(0, $reminders->execute(), 'The interview starts at 02:00 UTC, 40 minutes ahead.');

        $this->moveClockTo('2026-10-08 01:31:00');
        $this->assertSame(1, $reminders->execute());
        $this->assertSame(0, $reminders->execute());

        $this->assertSame(['interview.starting_soon'], $this->types($this->recruiterId, 'interview.starting_soon'));
        $this->assertSame(['interview.starting_soon'], $this->types($this->adminId, 'interview.starting_soon'));
        $this->assertSame([], $this->types($this->seekerId, 'interview.starting_soon'));
        $this->assertSame([], $this->types($this->secondRecruiterId, 'interview.starting_soon'));
        $this->assertSame('https://meet.example.test/room', json_decode((string) DB::table('notifications')->where('notifiable_id', $this->adminId)->where('type', 'interview.starting_soon')->value('data'), true)['meeting_url']);
        $this->assertNotNull(DB::table('recruitment_interviews')->value('starting_reminded_at'));
    }

    public function test_a_pending_interview_and_a_final_application_get_no_starting_notice(): void
    {
        $this->openInterview(confirm: false);
        $reminders = $this->app->make(SendStartingSoonReminders::class);

        $this->moveClockTo('2026-10-08 01:31:00');
        $this->assertSame(0, $reminders->execute());

        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();
        $this->assertSame(0, $reminders->execute());
    }

    public function test_the_scorecard_reminder_at_24_hours_is_emailed_to_the_missing_members(): void
    {
        $this->openInterview();
        $reminders = $this->app->make(SendScorecardReminders::class);

        $this->moveClockTo('2026-10-09 03:01:00');
        $this->assertSame(1, $reminders->execute());
        $this->assertSame(0, $reminders->escalate(), 'The second step waits for 72 hours.');

        $this->assertSame(['interview.scorecard_reminder'], $this->types($this->adminId, 'interview.scorecard_reminder'));
        $this->assertContains('Your scorecard for the Backend Developer interview is missing', $this->subjectsTo('andi@example.test'));
        $this->assertContains('Your scorecard for the Backend Developer interview is missing', $this->subjectsTo('rina@example.test'));
        $this->assertSame([], $this->subjectsTo('sari@example.test'));
    }

    public function test_the_scorecard_email_follows_its_own_preference_and_the_notice_stays(): void
    {
        $this->openInterview();
        $this->api('PUT', '/me/notification-preferences/team_scorecard_reminders/email', ['enabled' => false], $this->adminToken)->assertNoContent();

        $this->moveClockTo('2026-10-09 03:01:00');
        $this->app->make(SendScorecardReminders::class)->execute();

        $this->assertSame(['interview.scorecard_reminder'], $this->types($this->adminId, 'interview.scorecard_reminder'));
        $this->assertSame([], $this->scorecardSubjectsTo('andi@example.test'));
        $this->assertNotSame([], $this->scorecardSubjectsTo('rina@example.test'));
    }

    public function test_after_72_hours_the_missing_members_are_reminded_again_and_the_scheduler_is_told_the_count(): void
    {
        $this->openInterview();
        $reminders = $this->app->make(SendScorecardReminders::class);

        $this->moveClockTo('2026-10-09 03:01:00');
        $reminders->execute();

        $this->moveClockTo('2026-10-11 03:01:00');
        $this->assertSame(1, $reminders->escalate());
        $this->assertSame(0, $reminders->escalate());

        $this->assertContains('Your scorecard for the Backend Developer interview is missing (second reminder)', $this->subjectsTo('andi@example.test'));
        $this->assertSame(['interview.scorecards_missing'], $this->types($this->recruiterId, 'interview.scorecards_missing'));
        $this->assertSame([], $this->types($this->adminId, 'interview.scorecards_missing'), 'The admin is neither the scheduler nor the main interviewer.');
        $this->assertSame(2, json_decode((string) DB::table('notifications')->where('notifiable_id', $this->recruiterId)->where('type', 'interview.scorecards_missing')->value('data'), true)['missing']);
        $this->assertNotNull(DB::table('recruitment_interviews')->value('scorecard_escalated_at'));
    }

    public function test_the_command_runs_both_steps_and_the_scheduler_runs_the_starting_notice_every_five_minutes(): void
    {
        $this->openInterview();
        $this->moveClockTo('2026-10-11 03:01:00');

        $this->artisan('recruitment:send-interview-reminders')->expectsOutput('Followed up on missing scorecards of 1 interview a second time.')->assertSuccessful();
        $this->artisan('recruitment:send-starting-soon-reminders')->expectsOutput('Told 0 panels that an interview starts soon.')->assertSuccessful();

        $event = collect($this->app->make(Schedule::class)->events())
            ->first(static fn ($event): bool => str_contains((string) $event->command, 'recruitment:send-starting-soon-reminders'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
