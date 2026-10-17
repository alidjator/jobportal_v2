<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Recruitment\Application\SendStartingSoonReminders;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interviewers.md` section 2: the invitation of the panel with the calendar file, the answers and the cancellation. */
final class InterviewRsvpTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    private int $interview = 0;

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

    /** Schedules an online interview on the 8th, 09:00 Jakarta time, by the recruiter (also the main interviewer), with the admin on the panel. */
    private function openInterview(): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');
        $schedule = $this->interviewSchedule(['starts_at' => '2026-10-08T02:00:00Z', 'participant_ids' => [$this->adminId]]);
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $schedule], $this->recruiterToken)->assertNoContent();

        $this->interview = (int) DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->orderByDesc('id')->value('id');
    }

    private function moveClockTo(string $time): void
    {
        Carbon::setTestNow($time);
        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->secondRecruiterToken = $this->tokenFor($this->secondRecruiterId);
        $this->seekerToken = $this->tokenFor($this->seekerId);
    }

    private function rsvp(string $token, string $answer, ?string $note = null): TestResponse
    {
        return $this->api('POST', "/company/interviews/{$this->interview}/rsvp", ['answer' => $answer, 'note' => $note], $token);
    }

    private function panel(array $ids): TestResponse
    {
        return $this->api('PUT', "/company/interviews/{$this->interview}/participants", ['participant_ids' => $ids], $this->recruiterToken);
    }

    /** @return list<string> */
    private function types(int $userId, string $type): array
    {
        return DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', $type)->pluck('type')->all();
    }

    /** @return list<Email> the emails sent to the address */
    private function emailsTo(string $address): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();
        $found = [];

        foreach ($transport->messages()->all() as $sent) {
            $email = $sent->getOriginalMessage();

            if ($email instanceof Email && in_array($address, array_map(static fn ($to): string => $to->getAddress(), $email->getTo()), true)) {
                $found[] = $email;
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function subjectsTo(string $address): array
    {
        return array_map(static fn (Email $email): string => (string) $email->getSubject(), $this->emailsTo($address));
    }

    private function calendarTo(string $address, string $subjectStart): string
    {
        foreach ($this->emailsTo($address) as $email) {
            if (str_starts_with((string) $email->getSubject(), $subjectStart)) {
                foreach ($email->getAttachments() as $attachment) {
                    if ($attachment->getFilename() === 'interview.ics') {
                        return (string) $attachment->getBody();
                    }
                }
            }
        }

        return '';
    }

    /** @return array<string, mixed> */
    private function interviewJson(): array
    {
        return $this->api('GET', "/company/applications/{$this->applicationId}/interviews", [], $this->recruiterToken)->assertOk()->json('data.0');
    }

    public function test_scheduling_invites_the_interviewer_and_the_panel_with_a_calendar_file(): void
    {
        $this->openInterview();

        foreach (['rina@example.test', 'andi@example.test'] as $address) {
            $this->assertNotEmpty(array_filter($this->subjectsTo($address), static fn (string $subject): bool => str_starts_with($subject, 'Invitation to interview')));
        }

        $this->assertSame([], $this->subjectsTo('sari@example.test'));
        $this->assertSame(['interview.panel_invited'], $this->types($this->adminId, 'interview.panel_invited'));
        $this->assertSame([], $this->types($this->secondRecruiterId, 'interview.panel_invited'));

        $calendar = $this->calendarTo('andi@example.test', 'Invitation to interview');
        $this->assertStringContainsString('METHOD:REQUEST', $calendar);
        $this->assertStringContainsString('mailto:andi@example.test', $calendar);
    }

    public function test_the_invitation_follows_the_interview_preference_and_the_notice_stays(): void
    {
        $this->api('PUT', '/me/notification-preferences/team_interview_changes/email', ['enabled' => false], $this->adminToken)->assertNoContent();

        $this->openInterview();

        $this->assertSame([], $this->subjectsTo('andi@example.test'));
        $this->assertSame(['interview.panel_invited'], $this->types($this->adminId, 'interview.panel_invited'));
    }

    public function test_a_member_added_to_the_panel_is_invited_and_one_removed_gets_the_cancellation(): void
    {
        $this->openInterview();

        $this->panel([$this->secondRecruiterId])->assertOk();

        $this->assertNotEmpty(array_filter($this->subjectsTo('sari@example.test'), static fn (string $subject): bool => str_starts_with($subject, 'Invitation to interview')));
        $this->assertSame(['interview.panel_invited'], $this->types($this->secondRecruiterId, 'interview.panel_invited'));
        $this->assertNotEmpty(array_filter($this->subjectsTo('andi@example.test'), static fn (string $subject): bool => str_starts_with($subject, 'You are no longer on the interview')));
        $this->assertStringContainsString('METHOD:CANCEL', $this->calendarTo('andi@example.test', 'You are no longer on the interview'));
    }

    public function test_a_panel_change_that_changes_nothing_sends_nothing_and_keeps_the_answers(): void
    {
        $this->openInterview();
        $this->rsvp($this->adminToken, 'accepted')->assertOk();
        $before = count($this->emailsTo('andi@example.test'));

        $this->panel([$this->adminId])->assertOk();

        $this->assertCount($before, $this->emailsTo('andi@example.test'));
        $this->assertSame('accepted', $this->interviewJson()['participants'][0]['rsvp']['answer']);
    }

    public function test_the_answers_are_shown_on_the_interview_and_can_be_changed_until_it_starts(): void
    {
        $this->openInterview();
        $this->assertSame('pending', $this->interviewJson()['participants'][0]['rsvp']['answer']);
        $this->assertSame('pending', $this->interviewJson()['interviewer_rsvp']['answer']);

        $this->rsvp($this->adminToken, 'accepted', 'See you')->assertOk()->assertJsonPath('data.answer', 'accepted')->assertJsonPath('data.note', 'See you');
        $this->rsvp($this->recruiterToken, 'accepted')->assertOk();
        $this->rsvp($this->adminToken, 'declined', 'Clash')->assertOk()->assertJsonPath('data.answer', 'declined');

        $json = $this->interviewJson();
        $this->assertSame('declined', $json['participants'][0]['rsvp']['answer']);
        $this->assertSame('Clash', $json['participants'][0]['rsvp']['note']);
        $this->assertNotNull($json['participants'][0]['rsvp']['at']);
        $this->assertSame('accepted', $json['interviewer_rsvp']['answer']);
        $this->assertSame([$this->adminId], array_column($json['participants'], 'id'), 'The panel stays as it is.');

        $this->moveClockTo('2026-10-08 02:00:00');
        $this->rsvp($this->adminToken, 'accepted')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-started');
    }

    public function test_declining_tells_the_scheduler_in_app_and_by_email_with_the_note(): void
    {
        $this->openInterview();

        $this->rsvp($this->adminToken, 'declined', 'Another meeting')->assertOk();

        $this->assertSame(['interview.panel_declined'], $this->types($this->recruiterId, 'interview.panel_declined'));
        $this->assertContains('Andi Admin cannot attend the Backend Developer interview', $this->subjectsTo('rina@example.test'));
        $sent = array_values(array_filter($this->emailsTo('rina@example.test'), static fn (Email $email): bool => str_contains((string) $email->getSubject(), 'cannot attend')));
        $this->assertStringContainsString('Another meeting', (string) $sent[0]->getHtmlBody().(string) $sent[0]->getTextBody());
    }

    public function test_the_scheduler_declining_their_own_interview_and_an_accepted_answer_tell_nobody(): void
    {
        $this->openInterview();

        $this->rsvp($this->recruiterToken, 'declined')->assertOk();
        $this->rsvp($this->adminToken, 'accepted')->assertOk();

        $this->assertSame([], $this->types($this->recruiterId, 'interview.panel_declined'));
        $this->assertNotContains('Andi Admin cannot attend the Backend Developer interview', $this->subjectsTo('rina@example.test'));
    }

    public function test_only_the_panel_answers_and_the_answer_is_checked(): void
    {
        $this->openInterview();

        $this->assertContains($this->rsvp($this->secondRecruiterToken, 'accepted')->status(), [403, 404], 'A member who is not on the panel and does not see the job.');
        $this->rsvp($this->adminToken, 'maybe')->assertStatus(422);
        $this->rsvp($this->adminToken, 'pending')->assertStatus(422);
        $this->rsvp($this->adminToken, 'accepted', str_repeat('x', 501))->assertStatus(422);
    }

    public function test_an_interview_the_team_cancelled_cannot_be_answered_and_cancels_the_panel_calendar(): void
    {
        $this->openInterview();

        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();

        $this->assertNotEmpty(array_filter($this->subjectsTo('andi@example.test'), static fn (string $subject): bool => str_starts_with($subject, 'You are no longer on the interview')));
        $this->assertStringContainsString('METHOD:CANCEL', $this->calendarTo('andi@example.test', 'You are no longer on the interview'));
        $this->rsvp($this->adminToken, 'accepted')->assertStatus(409);
    }

    public function test_the_thirty_minute_notice_skips_a_member_who_declined(): void
    {
        $this->openInterview();
        $this->api('POST', "/me/applications/{$this->applicationId}/interview/confirm", [], $this->seekerToken)->assertOk();
        $this->rsvp($this->adminToken, 'declined')->assertOk();

        $this->moveClockTo('2026-10-08 01:31:00');
        $this->assertSame(1, $this->app->make(SendStartingSoonReminders::class)->execute());

        $this->assertSame(['interview.starting_soon'], $this->types($this->recruiterId, 'interview.starting_soon'));
        $this->assertSame([], $this->types($this->adminId, 'interview.starting_soon'));
    }
}
