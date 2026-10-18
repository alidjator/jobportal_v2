<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Recruitment\Infrastructure\Analytics\TransitionRows;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-withdrawals.md` sections 2 and 3: a withdrawal marked by the team and the notices to the job seeker, the assignees and the interviewers. */
final class TeamWithdrawalTest extends FeatureTestCase
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

    private function withdraw(array $body, ?string $token = null, ?int $id = null): TestResponse
    {
        return $this->api('POST', '/company/applications/'.($id ?? $this->applicationId).'/withdraw', $body, $token ?? $this->recruiterToken);
    }

    private function toInterviewStage(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('interview'), 'interview' => $this->interviewSchedule()], $this->recruiterToken)->assertOk();
    }

    private function row(): object
    {
        return DB::table('recruitment_applications')->where('id', $this->applicationId)->first();
    }

    /** @return list<string> */
    private function types(int $userId): array
    {
        $types = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', '%withdrawn%')->orWhere(fn ($q) => $q->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', 'interview.cancelled%'))->pluck('type')->all();
        sort($types);

        return $types;
    }

    /** @return list<Email> */
    private function emailsTo(string $address): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();

        return array_values(array_filter(
            array_map(fn ($sent): Email => $sent->getOriginalMessage(), $transport->messages()->all()),
            fn (Email $email): bool => $email->getTo()[0]->getAddress() === $address,
        ));
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

    public function test_the_team_marks_a_withdrawal_with_its_reason_and_note_and_the_applicant_may_apply_again(): void
    {
        $this->withdraw(['reason' => 'unreachable', 'note' => 'Stopped answering'])->assertNoContent();

        $row = $this->row();
        $this->assertSame('withdrawn', $row->stage_type);
        $this->assertNull($row->active_key);
        $this->assertSame('unreachable', $row->withdrawal_reason);
        $this->assertNull($row->withdrawn_note);

        $entry = DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->orderByDesc('id')->first();
        $this->assertSame(['withdrawn', 'recruiter', $this->recruiterId, 'unreachable', 'Stopped answering'], [$entry->to_type, $entry->actor_kind, (int) $entry->actor_id, $entry->withdrawal_reason, $entry->note]);

        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->adminToken)
            ->assertJsonPath('data.withdrawal.reason', 'unreachable')
            ->assertJsonPath('data.withdrawal.by', 'recruiter')
            ->assertJsonPath('data.withdrawal.actor.name', 'Rina Rekruter')
            ->assertJsonPath('data.withdrawal.note', 'Stopped answering');

        $mine = $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->seekerToken)->assertOk();
        $mine->assertJsonPath('data.withdrawal.reason', 'unreachable')->assertJsonPath('data.withdrawal.by', 'recruiter');
        $this->assertStringNotContainsString('Stopped answering', (string) $mine->getContent());

        $this->apply($this->job)->assertCreated();
    }

    public function test_the_reason_is_required_and_the_note_rules_hold(): void
    {
        foreach ([[], ['reason' => 'because'], ['reason' => '']] as $body) {
            $this->withdraw($body)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-withdrawal')->assertJsonStructure(['errors' => ['reason']]);
        }

        $this->withdraw(['reason' => 'other'])->assertStatus(422)->assertJsonStructure(['errors' => ['note']]);
        $this->withdraw(['reason' => 'salary', 'note' => str_repeat('x', 1001)])->assertStatus(422)->assertJsonStructure(['errors' => ['note']]);
        $this->assertSame('applied', $this->row()->stage_type);

        $this->withdraw(['reason' => 'other', 'note' => 'Moved abroad'])->assertNoContent();
    }

    public function test_only_the_members_who_may_see_the_job_can_mark_a_withdrawal(): void
    {
        $this->withdraw(['reason' => 'salary'], $this->seekerToken)->assertStatus(403);

        foreach ([$this->secondRecruiterToken, $this->otherAdminToken] as $token) {
            $this->withdraw(['reason' => 'salary'], $token)->assertStatus(404);
        }

        $this->withdraw(['reason' => 'salary'], id: 999999)->assertStatus(404);
        $this->assertSame('applied', $this->row()->stage_type);
    }

    public function test_a_final_or_hired_application_cannot_be_marked(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('hired'), 'hire' => ['join_date' => now()->addDays(30)->format('Y-m-d')]], $this->recruiterToken)->assertOk();
        $this->withdraw(['reason' => 'salary'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-not-withdrawable');

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertOk();
        $this->withdraw(['reason' => 'salary'])->assertNoContent();
        $this->withdraw(['reason' => 'salary'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-not-withdrawable');
    }

    public function test_the_applicant_is_told_once_and_the_cancelled_interview_sends_no_message_of_its_own(): void
    {
        $this->toInterviewStage();
        $before = count($this->emailsTo('siti@example.test'));

        $this->withdraw(['reason' => 'salary', 'note' => 'secret team note'], $this->adminToken)->assertNoContent();

        $this->assertSame('cancelled', DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->value('status'));
        $this->assertSame(['application.withdrawn_by_company'], array_values(array_diff($this->types($this->seekerId), ['interview.cancelled'])));
        $this->assertNotContains('interview.cancelled', $this->types($this->seekerId));

        $emails = array_slice($this->emailsTo('siti@example.test'), $before);
        $this->assertCount(1, $emails);

        $calendar = (string) $this->calendarOf($emails[0]);
        $this->assertStringContainsString("METHOD:CANCEL\r\n", $calendar);
        $this->assertStringContainsString("STATUS:CANCELLED\r\n", $calendar);

        $everything = $emails[0]->getTextBody().$emails[0]->getHtmlBody().$emails[0]->getSubject().json_encode(DB::table('notifications')->where('notifiable_id', $this->seekerId)->pluck('data'));
        $this->assertStringContainsString('Salary', $everything);
        $this->assertStringNotContainsString('secret team note', $everything);
    }

    public function test_the_assignees_and_the_interviewers_are_told_and_the_member_who_marked_it_is_left_out(): void
    {
        $this->toInterviewStage();
        $before = count($this->emailsTo('rina@example.test'));

        $this->withdraw(['reason' => 'personal'], $this->adminToken)->assertNoContent();

        $this->assertSame(['application.withdrawn', 'interview.cancelled_for_team'], $this->types($this->recruiterId));
        $this->assertSame([], $this->types($this->adminId));

        $notice = json_decode((string) DB::table('notifications')->where('notifiable_id', $this->recruiterId)->where('type', 'application.withdrawn')->value('data'), true);
        $this->assertSame(['application_id' => $this->applicationId, 'job_id' => $this->job, 'reason' => 'personal', 'by' => 'recruiter'], $notice);

        $emails = array_slice($this->emailsTo('rina@example.test'), $before);
        $this->assertCount(1, $emails);
        $this->assertStringContainsString("METHOD:CANCEL\r\n", (string) $this->calendarOf($emails[0]));
        $this->assertStringContainsString('Siti Rahma', (string) $emails[0]->getTextBody());
        $this->assertCount(0, $this->emailsTo('andi@example.test'));
    }

    public function test_the_member_who_marked_it_gets_no_notice_even_as_the_interviewer(): void
    {
        $this->toInterviewStage();
        $before = count($this->emailsTo('rina@example.test'));

        $this->withdraw(['reason' => 'personal'], $this->recruiterToken)->assertNoContent();

        $this->assertSame([], $this->types($this->recruiterId));
        $this->assertCount($before, $this->emailsTo('rina@example.test'));
    }

    public function test_a_withdrawal_by_the_applicant_tells_the_assignees_and_the_interviewers_but_not_the_applicant(): void
    {
        $this->toInterviewStage();

        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", ['reason' => 'location'], $this->seekerToken)->assertNoContent();

        $this->assertSame(['application.withdrawn', 'interview.cancelled_for_team'], $this->types($this->recruiterId));
        $this->assertNotContains('application.withdrawn_by_company', $this->types($this->seekerId));

        $notice = json_decode((string) DB::table('notifications')->where('notifiable_id', $this->recruiterId)->where('type', 'application.withdrawn')->value('data'), true);
        $this->assertSame(['application_id' => $this->applicationId, 'job_id' => $this->job, 'reason' => 'location', 'by' => 'applicant'], $notice);
    }

    public function test_the_audit_entry_holds_the_application_and_the_reason_but_not_the_note(): void
    {
        $this->withdraw(['reason' => 'salary', 'note' => 'secret team note'])->assertNoContent();

        $entries = DB::table('platform_audit_logs')->where('action', 'recruitment.application_withdrawn_by_company')->get();
        $this->assertCount(1, $entries);
        $this->assertSame($this->recruiterId, (int) $entries[0]->actor_id);
        $this->assertSame($this->applicationId, (int) $entries[0]->subject_id);
        $this->assertEquals(['reason' => 'salary', 'company_id' => $this->companyId], json_decode($entries[0]->metadata, true));
    }

    public function test_anonymisation_clears_the_team_note_but_keeps_the_reason_code(): void
    {
        $this->withdraw(['reason' => 'other', 'note' => 'Said she moved to Bali'])->assertNoContent();

        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        $entry = DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->where('to_type', 'withdrawn')->first();
        $this->assertNull($entry->note);
        $this->assertSame('other', $entry->withdrawal_reason);
        $this->assertSame('other', $this->row()->withdrawal_reason);
    }

    public function test_anonymisation_also_clears_the_note_of_a_rejection_but_keeps_its_reason_code(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'other', 'note' => 'Sounded like Siti from Bandung'], $this->recruiterToken)->assertNoContent();

        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        $entry = DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->where('to_type', 'rejected')->first();
        $this->assertNull($entry->note);
        $this->assertSame('other', $entry->rejection_reason);
    }

    public function test_the_analytics_rows_carry_the_withdrawal_reason_and_tell_the_team_from_the_applicant(): void
    {
        $this->withdraw(['reason' => 'salary'])->assertNoContent();
        $second = (int) $this->apply($this->job)->assertCreated()->json('data.id');
        $this->api('POST', "/me/applications/{$second}/withdraw", ['reason' => 'location'], $this->seekerToken)->assertNoContent();

        $rows = [];

        foreach (app(TransitionRows::class)->between('2000-01-01 00:00:00', '2100-01-01 00:00:00', null) as $row) {
            if ($row['to_type'] === 'withdrawn') {
                $rows[$row['reason']] = $row['actor_id'];
            }
        }

        $this->assertSame(['salary' => $this->recruiterId, 'location' => 0], $rows);
    }
}
