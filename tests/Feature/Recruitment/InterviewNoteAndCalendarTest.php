<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Recruitment\Application\SendInterviewReminders;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interview-calendar.md` sections 2, 3 and 4: the note for the candidate, the calendar file and the move without a schedule. */
final class InterviewNoteAndCalendarTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
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

    private function toInterviewStage(array $interview): TestResponse
    {
        return $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('interview'), 'interview' => $interview], $this->recruiterToken);
    }

    private function schedule(array $overrides = []): TestResponse
    {
        return $this->api('PUT', "/company/applications/{$this->applicationId}/interview", $this->interviewSchedule($overrides), $this->recruiterToken);
    }

    private function patchNote(mixed $note): TestResponse
    {
        return $this->api('PATCH', "/company/applications/{$this->applicationId}/interview", ['note' => $note], $this->recruiterToken);
    }

    private function mine(string $action = '', string $method = 'GET'): TestResponse
    {
        return $this->api($method, "/me/applications/{$this->applicationId}/interview".($action === '' ? '' : "/{$action}"), [], $this->seekerToken);
    }

    private function interviewRow(): object
    {
        return DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->orderByDesc('id')->first();
    }

    private function types(int $userId): array
    {
        $types = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', 'interview.%')->pluck('type')->all();
        sort($types);

        return $types;
    }

    /** @return list<Email> the emails to the job seeker */
    private function emailsToSeeker(): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();
        $all = array_map(fn ($sent) => $sent->getOriginalMessage(), $transport->messages()->all());

        return array_values(array_filter($all, fn ($email): bool => $email->getTo()[0]->getAddress() === 'siti@example.test'));
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

    /** @return list<Email> */
    private function emailsWithCalendar(): array
    {
        return array_values(array_filter($this->emailsToSeeker(), fn (Email $email): bool => $this->calendarOf($email) !== null));
    }

    // ----- the note for the candidate -----

    public function test_the_note_is_saved_with_the_schedule_and_the_job_seeker_sees_it(): void
    {
        $this->toInterviewStage($this->interviewSchedule(['note' => 'Please bring your ID card.']))->assertOk();

        $this->assertSame('Please bring your ID card.', $this->interviewRow()->note);
        $this->mine()->assertOk()->assertJsonPath('data.note', 'Please bring your ID card.');
        $emails = $this->emailsToSeeker();
        $scheduled = collect($emails)->first(fn (Email $email): bool => str_contains($email->getSubject() ?? '', 'Interview scheduled'));
        $this->assertStringContainsString('Note: Please bring your ID card.', (string) $scheduled->getTextBody());
    }

    public function test_a_new_time_keeps_the_note_unless_the_request_names_one(): void
    {
        $this->toInterviewStage($this->interviewSchedule(['note' => 'Bring your ID.']))->assertOk();

        $this->schedule(['starts_at' => now()->addDays(5)->utc()->format('Y-m-d\TH:i:s\Z')])->assertOk()->assertJsonPath('data.note', 'Bring your ID.');
        $this->schedule(['starts_at' => now()->addDays(6)->utc()->format('Y-m-d\TH:i:s\Z'), 'note' => 'Come to the lobby.'])->assertOk()->assertJsonPath('data.note', 'Come to the lobby.');
        $this->schedule(['starts_at' => now()->addDays(7)->utc()->format('Y-m-d\TH:i:s\Z'), 'note' => null])->assertOk()->assertJsonPath('data.note', null);
        $this->schedule(['note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonValidationErrors(['note']);
    }

    public function test_changing_only_the_note_updates_the_interview_in_place_and_tells_the_job_seeker(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        $this->mine('confirm', 'POST')->assertOk();
        $before = $this->interviewRow();
        $emailsBefore = count($this->emailsToSeeker());

        $this->patchNote('Bring your portfolio.')->assertOk()->assertJsonPath('data.note', 'Bring your portfolio.')->assertJsonPath('data.status', 'confirmed');

        $after = $this->interviewRow();
        $this->assertSame($before->id, $after->id);
        $this->assertSame('confirmed', $after->status);
        $this->assertSame($before->starts_at, $after->starts_at);
        $this->assertSame($before->proposals_used, $after->proposals_used);
        $this->assertGreaterThan($before->calendar_sequence, $after->calendar_sequence);
        $this->assertContains('interview.updated', $this->types($this->seekerId));
        $this->assertCount($emailsBefore + 1, $this->emailsToSeeker());

        $update = collect($this->emailsToSeeker())->last();
        $this->assertStringContainsString('updated', (string) $update->getSubject());
        $this->assertStringContainsString('Note: Bring your portfolio.', (string) $update->getTextBody());
        $this->assertStringContainsString('SEQUENCE:'.$after->calendar_sequence, (string) $this->calendarOf($update));
    }

    public function test_the_same_note_again_sends_nothing_and_a_null_note_clears_it(): void
    {
        $this->toInterviewStage($this->interviewSchedule(['note' => 'Bring your ID.']))->assertOk();
        $emails = count($this->emailsToSeeker());
        $sequence = $this->interviewRow()->calendar_sequence;

        $this->patchNote('  Bring your ID.  ')->assertOk();
        $this->assertCount($emails, $this->emailsToSeeker());
        $this->assertSame($sequence, $this->interviewRow()->calendar_sequence);
        $this->assertNotContains('interview.updated', $this->types($this->seekerId));

        $this->patchNote(null)->assertOk()->assertJsonPath('data.note', null);
        $this->assertCount($emails + 1, $this->emailsToSeeker());
    }

    public function test_the_note_cannot_change_without_an_active_interview_and_needs_the_right(): void
    {
        $this->patchNote('Hello')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-not-active');

        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        $this->api('PATCH', "/company/applications/{$this->applicationId}/interview", [], $this->recruiterToken)->assertStatus(422)->assertJsonValidationErrors(['note']);
        $this->patchNote(str_repeat('a', 1001))->assertStatus(422)->assertJsonValidationErrors(['note']);

        $viewer = $this->memberWith(['applications.view'], 'viewer@example.test');
        DB::table('job_assignments')->insert(['job_id' => $this->job, 'user_id' => $viewer['id'], 'created_at' => now()]);
        $this->api('PATCH', "/company/applications/{$this->applicationId}/interview", ['note' => 'x'], $viewer['token'])->assertForbidden()->assertJsonPath('permission', 'interviews.manage');

        $this->api('POST', "/company/applications/{$this->applicationId}/interview/cancel", [], $this->recruiterToken)->assertOk();
        $this->patchNote('Hello')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-not-active');
    }

    // ----- the calendar file -----

    public function test_the_emails_carry_one_event_whose_uid_stays_and_whose_sequence_rises(): void
    {
        $this->toInterviewStage($this->interviewSchedule(['note' => 'Bring, your ID; thanks']))->assertOk();
        $first = $this->calendarOf(collect($this->emailsWithCalendar())->first());

        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", (string) $first);
        $this->assertStringContainsString("METHOD:REQUEST\r\n", (string) $first);
        $this->assertStringContainsString('ATTENDEE;CN="Siti Rahma";ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=FALSE:mailto:siti@example.test', str_replace("\r\n ", '', (string) $first));
        $this->assertStringContainsString('SUMMARY:Interview: Backend Developer - ', (string) $first);
        $this->assertStringContainsString('Note: Bring\, your ID\; thanks', str_replace("\r\n ", '', (string) $first));
        $this->assertMatchesRegularExpression('/^DTSTART:\d{8}T\d{6}Z\r$/m', (string) $first);
        $this->assertStringNotContainsString("\n\n", str_replace("\r\n", "\n", rtrim((string) $first)));

        preg_match('/^UID:(.+)\r$/m', (string) $first, $uid);
        $row = $this->interviewRow();
        $this->assertSame(trim($uid[1]), $row->calendar_uid.'@onesis');

        $this->schedule(['starts_at' => now()->addDays(6)->utc()->format('Y-m-d\TH:i:s\Z')])->assertOk();
        $second = $this->calendarOf(collect($this->emailsWithCalendar())->last());
        $this->assertStringContainsString($uid[0], (string) $second);

        $rows = DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame($rows[0]->calendar_uid, $rows[1]->calendar_uid);
        $this->assertGreaterThan($rows[0]->calendar_sequence, $rows[1]->calendar_sequence);
        $this->assertStringContainsString('SEQUENCE:'.$rows[1]->calendar_sequence."\r\n", (string) $second);
    }

    public function test_a_cancellation_and_a_decline_send_a_cancel_file(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        $this->api('POST', "/company/applications/{$this->applicationId}/interview/cancel", [], $this->recruiterToken)->assertOk();

        $cancel = $this->calendarOf(collect($this->emailsWithCalendar())->last());
        $this->assertStringContainsString("METHOD:CANCEL\r\n", (string) $cancel);
        $this->assertStringContainsString("STATUS:CANCELLED\r\n", (string) $cancel);
        $cancelledSequence = $this->interviewRow()->calendar_sequence;
        $this->assertStringContainsString('SEQUENCE:'.$cancelledSequence."\r\n", (string) $cancel);

        $this->schedule()->assertCreated();
        $count = count($this->emailsWithCalendar());
        $this->mine('decline', 'POST')->assertOk();

        $this->assertCount($count + 1, $this->emailsWithCalendar());
        $declined = collect($this->emailsWithCalendar())->last();
        $this->assertStringContainsString('You declined', (string) $declined->getSubject());
        $this->assertStringContainsString("METHOD:CANCEL\r\n", (string) $this->calendarOf($declined));
    }

    public function test_the_reminder_carries_no_calendar_file(): void
    {
        $this->toInterviewStage($this->interviewSchedule(['starts_at' => now()->addHours(5)->utc()->format('Y-m-d\TH:i:s\Z')]))->assertOk();
        $this->mine('confirm', 'POST')->assertOk();
        $this->app->make(SendInterviewReminders::class)->execute();

        $reminder = collect($this->emailsToSeeker())->first(fn (Email $email): bool => str_contains($email->getSubject() ?? '', 'Reminder'));
        $this->assertNotNull($reminder);
        $this->assertNull($this->calendarOf($reminder));
    }

    public function test_the_team_downloads_a_published_file_without_the_attendee(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        $id = (int) $this->interviewRow()->id;

        $response = $this->api('GET', "/company/interviews/{$id}/calendar.ics", token: $this->recruiterToken)->assertOk();
        $this->assertStringStartsWith('text/calendar', (string) $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringContainsString("METHOD:PUBLISH\r\n", $body);
        $this->assertStringNotContainsString('ATTENDEE', $body);
        $this->assertStringNotContainsString('siti@example.test', $body);

        $stranger = $this->memberWith(['applications.view'], 'stranger@example.test');
        $this->api('GET', "/company/interviews/{$id}/calendar.ics", token: $stranger['token'])->assertNotFound();
        $this->api('GET', '/company/interviews/999999/calendar.ics', token: $this->recruiterToken)->assertNotFound();
        $this->api('GET', "/company/interviews/{$id}/calendar.ics", token: $this->seekerToken)->assertForbidden();
    }

    public function test_a_long_line_is_folded_at_75_octets_without_breaking_a_character(): void
    {
        $note = str_repeat('Jalan Sudirman nomor 1, Jakarta Pusat — lantai 5 ', 6);
        $this->toInterviewStage($this->interviewSchedule(['note' => $note]))->assertOk();
        $file = (string) $this->calendarOf(collect($this->emailsWithCalendar())->first());

        foreach (explode("\r\n", $file) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
    }

    // ----- moving without a schedule -----

    private function pipeline(string $query = ''): TestResponse
    {
        return $this->api('GET', "/company/jobs/{$this->job}/applications{$query}", token: $this->recruiterToken);
    }

    public function test_a_move_can_say_the_time_comes_later_and_the_application_shows_as_not_scheduled(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('interview')], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:schedule-required');
        $this->pipeline()->assertOk()->assertJsonPath('data.0.interview_state', null);

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('interview'), 'interview' => ['schedule_later' => true]], $this->recruiterToken)->assertOk();

        $this->assertSame(0, DB::table('recruitment_interviews')->count());
        $this->assertSame([], $this->types($this->seekerId));
        $this->pipeline()->assertOk()->assertJsonPath('data.0.interview_state', 'not_scheduled');
        $this->pipeline('?filter[interview_state]=not_scheduled')->assertOk()->assertJsonCount(1, 'data');
        $this->pipeline('?filter[interview_state]=pending')->assertOk()->assertJsonCount(0, 'data');
        $this->mine()->assertOk()->assertJsonPath('data', null);

        $this->schedule()->assertCreated();
        $this->pipeline()->assertJsonPath('data.0.interview_state', 'pending');
        $this->pipeline('?filter[interview_state]=pending')->assertJsonCount(1, 'data');
        $this->pipeline('?filter[interview_state]=not_scheduled')->assertJsonCount(0, 'data');

        $this->mine('confirm', 'POST')->assertOk();
        $this->pipeline()->assertJsonPath('data.0.interview_state', 'confirmed');

        $this->api('POST', "/company/applications/{$this->applicationId}/interview/complete", [], $this->recruiterToken)->assertOk();
        $this->pipeline()->assertJsonPath('data.0.interview_state', 'completed');
        $this->pipeline('?filter[interview_state]=completed')->assertJsonCount(1, 'data');
    }

    public function test_a_cancelled_interview_leaves_the_application_not_scheduled(): void
    {
        $this->toInterviewStage($this->interviewSchedule())->assertOk();
        $this->api('POST', "/company/applications/{$this->applicationId}/interview/cancel", [], $this->recruiterToken)->assertOk();

        $this->pipeline()->assertJsonPath('data.0.interview_state', 'not_scheduled');
        $this->pipeline('?filter[interview_state]=not_scheduled')->assertJsonCount(1, 'data');
    }

    public function test_later_cannot_come_with_a_schedule_or_for_another_stage(): void
    {
        $this->toInterviewStage([...$this->interviewSchedule(), 'schedule_later' => true])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('screening'), 'interview' => ['schedule_later' => true]], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-schedule');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('interview'), 'interview' => ['schedule_later' => false]], $this->recruiterToken)
            ->assertStatus(422)->assertJsonValidationErrors(['interview.starts_at']);
        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'));
    }

    public function test_a_bulk_move_can_schedule_later_too(): void
    {
        $second = $this->seeker('budi.bulk@example.test', name: 'Budi Santoso');
        $token = $this->tokenFor($second);
        $secondId = (int) $this->api('POST', "/jobs/{$this->job}/applications", ['resume_id' => $this->completeProfile($token)], $token)->assertCreated()->json('data.id');

        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", [
            'application_ids' => [$this->applicationId, $secondId], 'stage_id' => $this->stage('interview'), 'interview' => ['schedule_later' => true],
        ], $this->recruiterToken)->assertOk()->assertJsonPath('data.0.status', 'moved')->assertJsonPath('data.1.status', 'moved');

        $this->assertSame(0, DB::table('recruitment_interviews')->count());
        $this->pipeline('?filter[interview_state]=not_scheduled')->assertJsonCount(2, 'data');

        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", [
            'application_ids' => [$this->applicationId], 'stage_id' => $this->stage('screening'), 'interview' => ['schedule_later' => true],
        ], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-schedule');
    }
}
