<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\SendStartingSoonReminders;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interview-reschedule.md`: moving a confirmed interview, refusing a proposed time, no-shows and the job seeker's reminder two hours before. */
final class InterviewRescheduleTest extends FeatureTestCase
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
            $this->mine('confirm')->assertOk();
        }
    }

    private function moveClockTo(string $time): void
    {
        Carbon::setTestNow($time);
        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->seekerToken = $this->tokenFor($this->seekerId);
    }

    private function mine(string $action, array $body = []): TestResponse
    {
        return $this->api('POST', "/me/applications/{$this->applicationId}/interview/{$action}", $body, $this->seekerToken);
    }

    private function team(string $action, array $body = [], ?string $token = null): TestResponse
    {
        return $this->api('POST', "/company/applications/{$this->applicationId}/interview/{$action}", $body, $token ?? $this->recruiterToken);
    }

    /** @return list<string> */
    private function types(int $userId, string $like = 'interview.%'): array
    {
        return DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', $like)->pluck('type')->all();
    }

    /** @return list<Email> */
    private function emailsTo(string $address): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();
        $all = array_map(fn ($sent) => $sent->getOriginalMessage(), $transport->messages()->all());

        return array_values(array_filter($all, fn (Email $email): bool => in_array($address, array_map(static fn ($to): string => $to->getAddress(), $email->getTo()), true)));
    }

    private function emailWithSubject(string $address, string $subject): ?Email
    {
        foreach ($this->emailsTo($address) as $email) {
            if ((string) $email->getSubject() === $subject) {
                return $email;
            }
        }

        return null;
    }

    private function calendarOf(Email $email): ?string
    {
        foreach ($email->getAttachments() as $part) {
            if ($part->getFilename() === 'interview.ics') {
                return $part->getBody();
            }
        }

        return null;
    }

    // ----- asking to move a confirmed interview -----

    public function test_a_confirmed_interview_stays_at_its_time_while_the_request_to_move_it_waits(): void
    {
        $this->openInterview();

        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:reason-required');
        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z', 'reason' => str_repeat('a', 501)])->assertStatus(422);

        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z', 'reason' => 'Ada ujian di kampus.'])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.starts_at', '2026-10-08T02:00:00+00:00')
            ->assertJsonPath('data.proposals_left', 1)
            ->assertJsonPath('data.proposal.starts_at', '2026-10-09T03:00:00+00:00')
            ->assertJsonPath('data.proposal.reason', 'Ada ujian di kampus.')
            ->assertJsonPath('data.proposal.made_at', '2026-10-05T09:00:00+00:00');

        $this->mine('propose', ['starts_at' => '2026-10-09T05:00:00Z', 'reason' => 'Lagi'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:proposal-pending');

        $data = json_decode((string) DB::table('notifications')->where('notifiable_id', $this->recruiterId)->where('type', 'interview.proposed')->value('data'), true);
        $this->assertSame('Ada ujian di kampus.', $data['reason']);
        $this->assertSame('2026-10-09T03:00:00+00:00', $data['proposed_starts_at']);
        $this->assertNotSame([], array_filter($this->emailsTo('rina@example.test'), static fn (Email $email): bool => str_contains((string) $email->getSubject(), 'move') || str_contains((string) $email->getSubject(), 'pindah')));

        $this->api('GET', "/company/applications/{$this->applicationId}/interviews", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.0.proposal.reason', 'Ada ujian di kampus.');
    }

    public function test_the_job_seeker_sees_until_when_a_confirmed_interview_can_be_moved(): void
    {
        $this->openInterview(confirm: false);
        $mine = fn (): TestResponse => $this->api('GET', "/me/applications/{$this->applicationId}/interview", token: $this->seekerToken)->assertOk();
        $mine()->assertJsonPath('data.reschedule_until', null);

        $this->mine('confirm')->assertOk()->assertJsonPath('data.reschedule_until', '2026-10-07T02:00:00+00:00');
        $this->app->make(SettingsStore::class)->setCompanyOverride('recruitment.interview_reschedule_cutoff_hours', $this->companyId, 6, null);
        $mine()->assertJsonPath('data.reschedule_until', '2026-10-07T20:00:00+00:00');

        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z', 'reason' => 'Bentrok'])->assertOk()->assertJsonPath('data.reschedule_until', null);
        $this->api('GET', "/company/applications/{$this->applicationId}/interviews", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.0.reschedule_until', null);
    }

    public function test_the_request_closes_at_the_cut_off_and_the_new_time_must_be_as_far_ahead(): void
    {
        $this->openInterview();

        $this->mine('propose', ['starts_at' => '2026-10-06T07:00:00Z', 'reason' => 'Bentrok'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview');

        $this->moveClockTo('2026-10-07 03:00:00');
        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z', 'reason' => 'Bentrok'])->assertStatus(409)
            ->assertJsonPath('type', 'urn:onesis:error:reschedule-too-late')
            ->assertJsonPath('cutoff_hours', 24);

        // The company may allow it until the start.
        $this->app->make(SettingsStore::class)->setCompanyOverride('recruitment.interview_reschedule_cutoff_hours', $this->companyId, 0, null);
        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z', 'reason' => 'Bentrok'])->assertOk();
        $this->mine('decline')->assertOk()->assertJsonPath('data.status', 'declined');
    }

    public function test_the_quota_is_shared_with_the_proposals_before_the_confirmation(): void
    {
        $this->openInterview(confirm: false);
        $this->mine('propose', ['starts_at' => '2026-10-08T05:00:00Z'])->assertOk()->assertJsonPath('data.status', 'pending');
        $this->team('accept-proposal')->assertOk()->assertJsonPath('data.status', 'confirmed');
        DB::table('recruitment_interviews')->update(['proposals_used' => 2]);

        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z', 'reason' => 'Bentrok'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:proposal-limit');
    }

    // ----- the team's answer -----

    public function test_the_team_refuses_and_the_job_seeker_gets_the_message_which_is_not_stored(): void
    {
        $this->openInterview();
        $this->team('refuse-proposal')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-proposal');
        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z', 'reason' => 'Bentrok'])->assertOk();

        $this->team('refuse-proposal', ['message' => 'Panel penuh pada waktu itu.'])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.starts_at', '2026-10-08T02:00:00+00:00')
            ->assertJsonPath('data.proposal', null)
            ->assertJsonPath('data.proposals_left', 1);

        $this->assertSame(['interview.proposal_refused'], $this->types($this->seekerId, 'interview.proposal_refused'));
        $email = $this->emailWithSubject('siti@example.test', 'Your proposed interview time for Backend Developer was not accepted');
        $this->assertNotNull($email);
        $this->assertStringContainsString('Panel penuh pada waktu itu.', (string) $email->getTextBody());
        $this->assertNull($this->calendarOf($email));
        $this->assertSame(0, DB::table('notifications')->where('data', 'like', '%Panel penuh%')->count());
        $this->assertNull(DB::table('recruitment_interviews')->value('proposal_reason'));
    }

    public function test_a_pending_interview_stays_pending_when_its_proposal_is_refused(): void
    {
        $this->openInterview(confirm: false);
        $this->mine('propose', ['starts_at' => '2026-10-08T05:00:00Z'])->assertOk();

        $this->team('refuse-proposal')->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.proposed_starts_at', null);
        $this->mine('confirm')->assertOk();
    }

    public function test_an_accepted_proposal_sends_the_new_calendar_and_invites_the_panel_again(): void
    {
        $this->openInterview();
        $interviewId = (int) DB::table('recruitment_interviews')->value('id');
        $this->api('POST', "/company/interviews/{$interviewId}/rsvp", ['answer' => 'accepted'], $this->adminToken)->assertOk();
        DB::table('recruitment_interviews')->update(['candidate_soon_reminded_at' => '2026-10-05 07:00:00']);
        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z', 'reason' => 'Bentrok'])->assertOk();
        $invitationsBefore = count($this->emailsTo('andi@example.test'));

        $this->team('accept-proposal')->assertOk()
            ->assertJsonPath('data.starts_at', '2026-10-09T03:00:00+00:00')
            ->assertJsonPath('data.participants.0.rsvp.answer', 'pending')
            ->assertJsonPath('data.interviewer_rsvp.answer', 'pending');

        $email = $this->emailWithSubject('siti@example.test', 'Your proposed interview time for Backend Developer was accepted');
        $this->assertNotNull($email);
        $calendar = (string) $this->calendarOf($email);
        $this->assertStringContainsString('METHOD:REQUEST', $calendar);
        $this->assertStringContainsString('DTSTART:20261009T030000Z', $calendar);

        $this->assertGreaterThan($invitationsBefore, count($this->emailsTo('andi@example.test')));
        $this->assertContains('interview.panel_invited', $this->types($this->adminId));
        $this->assertNull(DB::table('recruitment_interviews')->value('candidate_soon_reminded_at'));
    }

    public function test_an_unanswered_proposal_lapses_at_the_current_start(): void
    {
        $this->openInterview();
        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z', 'reason' => 'Bentrok'])->assertOk();

        $this->moveClockTo('2026-10-08 02:00:00');
        $this->artisan('recruitment:send-starting-soon-reminders')->expectsOutput('1 unanswered proposal lapsed.')->assertSuccessful();

        $row = DB::table('recruitment_interviews')->first();
        $this->assertSame('confirmed', $row->status);
        $this->assertNull($row->proposed_starts_at);
        $this->assertNull($row->proposal_reason);
    }

    public function test_the_task_list_counts_the_requests_and_the_interview_list_opens_them(): void
    {
        $this->openInterview();
        $this->mine('propose', ['starts_at' => '2026-10-09T03:00:00Z', 'reason' => 'Bentrok'])->assertOk();

        $items = $this->api('GET', '/company/tasks', token: $this->recruiterToken)->assertOk()->json('data.items');
        $task = collect($items)->firstWhere('key', 'reschedule_requests');
        $this->assertSame(1, $task['count']);
        $this->assertSame('company/interviews?filter[proposal]=open', $task['filter']);

        $this->api('GET', '/company/interviews?filter[proposal]=open', token: $this->recruiterToken)->assertOk()->assertJsonCount(1, 'data');
        $this->api('GET', '/company/interviews?filter[proposal]=open', token: $this->adminToken)->assertOk()->assertJsonCount(0, 'data');

        $this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.0.interview_proposal_open', true);
    }

    // ----- no-show -----

    public function test_a_no_show_is_marked_after_the_start_and_the_job_seeker_is_told(): void
    {
        $this->openInterview(confirm: false);
        $this->moveClockTo('2026-10-08 02:30:00');
        $this->team('no-show')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-not-confirmed');

        $this->moveClockTo('2026-10-05 09:00:00');
        $this->mine('confirm')->assertOk();
        $this->team('no-show')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-not-started');

        $this->moveClockTo('2026-10-08 02:30:00');
        $this->team('no-show', ['note' => 'Tidak bisa dihubungi.'])->assertOk()
            ->assertJsonPath('data.status', 'no_show')
            ->assertJsonPath('data.no_show_note', 'Tidak bisa dihubungi.');

        $this->assertSame(['interview.no_show'], $this->types($this->seekerId, 'interview.no_show'));
        $email = $this->emailWithSubject('siti@example.test', 'You were recorded as absent from the interview for Backend Developer');
        $this->assertNotNull($email);
        $this->assertNull($this->calendarOf($email));
        $this->assertStringNotContainsString('Tidak bisa dihubungi.', (string) $email->getTextBody());

        $this->api('GET', "/me/applications/{$this->applicationId}/interview", token: $this->seekerToken)->assertOk()->assertJsonPath('data', null);
        $this->api('GET', "/company/applications/{$this->applicationId}/interviews", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.0.status', 'no_show');
    }

    public function test_a_mistaken_no_show_is_corrected_to_completed_without_a_notice(): void
    {
        $this->openInterview();
        $this->moveClockTo('2026-10-08 03:30:00');
        $this->team('no-show')->assertOk();
        $notices = count($this->types($this->seekerId));
        $emails = count($this->emailsTo('siti@example.test'));

        $this->team('complete')->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertSame($notices, count($this->types($this->seekerId)));
        $this->assertSame($emails, count($this->emailsTo('siti@example.test')));
        $this->team('complete')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-active-interview');
    }

    public function test_after_a_no_show_the_application_can_get_a_new_interview(): void
    {
        $this->openInterview();
        $this->moveClockTo('2026-10-08 03:30:00');
        $this->team('no-show')->assertOk();

        $this->api('PUT', "/company/applications/{$this->applicationId}/interview", $this->interviewSchedule(), $this->recruiterToken)->assertCreated();
    }

    // ----- the reminder two hours before -----

    public function test_the_job_seeker_is_reminded_once_two_hours_before_in_app_and_by_email(): void
    {
        $this->openInterview();
        $reminders = $this->app->make(SendStartingSoonReminders::class);

        $this->moveClockTo('2026-10-07 23:00:00');
        $this->assertSame(0, $reminders->remindCandidates());

        $this->moveClockTo('2026-10-08 00:30:00');
        $this->assertSame(1, $reminders->remindCandidates());
        $this->assertSame(0, $reminders->remindCandidates());

        $this->assertSame(['interview.starting_soon'], $this->types($this->seekerId, 'interview.starting_soon'));
        $email = $this->emailWithSubject('siti@example.test', 'Your interview for Backend Developer starts soon');
        $this->assertNotNull($email);
        $this->assertNull($this->calendarOf($email));
        $this->assertStringContainsString('https://meet.example.test/room', (string) $email->getTextBody());
    }

    public function test_a_pending_interview_or_a_final_application_gets_no_reminder(): void
    {
        $this->openInterview(confirm: false);
        $reminders = $this->app->make(SendStartingSoonReminders::class);

        $this->moveClockTo('2026-10-08 00:30:00');
        $this->assertSame(0, $reminders->remindCandidates());

        DB::table('recruitment_interviews')->update(['status' => 'confirmed']);
        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['stage_type' => 'rejected']);
        $this->assertSame(0, $reminders->remindCandidates());
        $this->assertSame([], $this->types($this->seekerId, 'interview.starting_soon'));
    }
}
