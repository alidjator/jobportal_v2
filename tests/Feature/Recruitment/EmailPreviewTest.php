<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interview-calendar.md` section 5: the email an interview, a test or an offer would send, shown before it is sent, with nothing saved. */
final class EmailPreviewTest extends FeatureTestCase
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
        DB::table('company_companies')->where('id', $this->companyId)->update(['website' => 'https://www.maju.example']);
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function preview(array $body, ?string $token = null, ?int $id = null): TestResponse
    {
        return $this->api('POST', '/company/applications/'.($id ?? $this->applicationId).'/email-preview', $body, $token ?? $this->recruiterToken);
    }

    /** @return array<string, mixed> */
    private function interviewBody(array $overrides = []): array
    {
        return ['scenario' => 'interview_scheduled', ...$this->interviewSchedule($overrides)];
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function sentCount(): int
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();

        return count($transport->messages()->all());
    }

    /** @return array{emails: int, notifications: int, audit: int, history: int, stage: string} */
    private function footprint(): array
    {
        return [
            'emails' => $this->sentCount(),
            'notifications' => DB::table('notifications')->count(),
            'audit' => DB::table('platform_audit_logs')->count(),
            'history' => DB::table('recruitment_interviews')->count() + DB::table('recruitment_offers')->count() + DB::table('assessment_invitations')->count(),
            'stage' => (string) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'),
        ];
    }

    private function publishedTestId(): int
    {
        $id = $this->newTest('company', ['title' => 'Logic and numbers']);
        $this->sectionWithQuestion('company', $id);
        $this->company('POST', "tests/{$id}/publish")->assertOk();

        return $id;
    }

    // ----- the interview -----

    public function test_a_new_interview_is_shown_as_the_email_the_applicant_would_get_and_nothing_is_saved(): void
    {
        $before = $this->footprint();

        $data = $this->preview($this->interviewBody(['note' => 'Please bring your ID card.']))->assertOk()->json('data');

        $this->assertSame('recruitment.interview_scheduled', $data['template']);
        $this->assertSame('Interview scheduled for Backend Developer', $data['subject']);
        $this->assertSame('en', $data['locale']);
        $this->assertTrue($data['email_enabled']);
        $this->assertTrue($data['calendar_attached']);
        $this->assertStringContainsString('Siti Rahma', $data['text']);
        $this->assertStringContainsString('WIB', $data['text']);
        $this->assertStringContainsString('https://meet.example.test/room', $data['text']);
        $this->assertStringContainsString('Note: Please bring your ID card.', $data['text']);
        $this->assertStringContainsString('<strong>PT-MAJU</strong>', $data['html']);
        $this->assertStringContainsString('never charge a fee in a recruitment process', $data['html']);
        $this->assertStringContainsString('never charge a fee in a recruitment process', $data['text']);

        $this->assertSame($before, $this->footprint());
    }

    public function test_a_new_time_over_an_active_interview_is_shown_as_a_reschedule_and_the_interview_stays(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('interview'), 'interview' => $this->interviewSchedule(['note' => 'Bring your ID.'])], $this->recruiterToken)->assertNoContent();
        $row = DB::table('recruitment_interviews')->first();
        $before = $this->footprint();

        $data = $this->preview($this->interviewBody(['starts_at' => now()->addDays(8)->utc()->format('Y-m-d\TH:i:s\Z')]))->assertOk()->json('data');

        $this->assertSame('recruitment.interview_rescheduled', $data['template']);
        $this->assertStringContainsString('Note: Bring your ID.', $data['text']);
        $this->assertSame($before, $this->footprint());
        $this->assertEquals($row, DB::table('recruitment_interviews')->first());
        $this->assertSame('pending', DB::table('recruitment_interviews')->value('status'));
    }

    public function test_the_request_is_checked_by_the_rules_of_the_real_one(): void
    {
        $this->preview($this->interviewBody(['starts_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z')]))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview');
        $this->preview($this->interviewBody(['interviewer_id' => $this->seekerId]))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interviewer');
        $this->preview($this->interviewBody(['note' => 'Pay at https://evil.example/pay']))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        $this->preview(['scenario' => 'interview_scheduled'])->assertStatus(422)->assertJsonValidationErrors(['starts_at', 'duration_minutes', 'format', 'interviewer_id']);
        $this->preview(['scenario' => 'party'])->assertStatus(422)->assertJsonValidationErrors(['scenario']);
        $this->preview([])->assertStatus(422)->assertJsonValidationErrors(['scenario']);
    }

    public function test_a_note_change_is_shown_as_the_updated_email_and_needs_an_active_interview(): void
    {
        $this->preview(['scenario' => 'interview_updated', 'note' => 'Use the lobby door.'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:interview-not-active');

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('interview'), 'interview' => $this->interviewSchedule(['note' => 'Old note.'])], $this->recruiterToken)->assertNoContent();
        $before = $this->footprint();

        $data = $this->preview(['scenario' => 'interview_updated', 'note' => 'Use the lobby door.'])->assertOk()->json('data');

        $this->assertSame('recruitment.interview_updated', $data['template']);
        $this->assertStringContainsString('Note: Use the lobby door.', $data['text']);
        $this->assertSame('Old note.', DB::table('recruitment_interviews')->value('note'));
        $this->assertSame($before, $this->footprint());

        $this->preview(['scenario' => 'interview_updated', 'note' => null])->assertOk()->assertJsonPath('data.template', 'recruitment.interview_updated');
        $this->preview(['scenario' => 'interview_updated', 'note' => 'Mail hr@gmail.com'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        $this->preview(['scenario' => 'interview_updated'])->assertStatus(422)->assertJsonValidationErrors(['note']);
    }

    // ----- the test and the offer -----

    public function test_a_test_is_shown_with_its_title_due_time_and_note_and_nothing_is_saved(): void
    {
        $testId = $this->publishedTestId();
        $before = $this->footprint();
        $due = Carbon::now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z');

        $data = $this->preview(['scenario' => 'assessment_invited', 'test_id' => $testId, 'due_at' => $due, 'note' => 'Use a quiet room.'])->assertOk()->json('data');

        $this->assertSame('assessment.test_invited', $data['template']);
        $this->assertSame('A test for your application for Backend Developer', $data['subject']);
        $this->assertFalse($data['calendar_attached']);
        $this->assertStringContainsString('Logic and numbers', $data['text']);
        $this->assertStringContainsString('Note: Use a quiet room.', $data['text']);
        $this->assertStringContainsString('<strong>PT-MAJU</strong>', $data['html']);
        $this->assertSame($before, $this->footprint());

        $this->preview(['scenario' => 'assessment_invited', 'test_id' => 999_999, 'due_at' => $due])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-assessment-test');
        $this->preview(['scenario' => 'assessment_invited', 'test_id' => $testId, 'due_at' => Carbon::now()->addMinutes(5)->utc()->format('Y-m-d\TH:i:s\Z')])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-assessment-schedule');
        $this->preview(['scenario' => 'assessment_invited', 'test_id' => $testId, 'due_at' => $due, 'note' => 'Open bit.ly/x'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
    }

    public function test_an_offer_is_shown_with_its_values_and_a_second_one_is_a_revision(): void
    {
        $before = $this->footprint();
        $body = ['scenario' => 'offer_sent', ...$this->offerDetails(['note' => 'Welcome aboard.'])];

        $data = $this->preview($body)->assertOk()->json('data');

        $this->assertSame('recruitment.offer_sent', $data['template']);
        $this->assertSame('Job offer for Backend Developer', $data['subject']);
        $this->assertStringContainsString('Rp 12.000.000', $data['text']);
        $this->assertStringContainsString('Note: Welcome aboard.', $data['text']);
        $this->assertSame($before, $this->footprint());

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails()], $this->recruiterToken)->assertNoContent();
        $this->preview($body)->assertOk()->assertJsonPath('data.template', 'recruitment.offer_revised');
        $this->assertSame(1, DB::table('recruitment_offers')->count());
        $this->assertSame('pending', DB::table('recruitment_offers')->value('status'));

        $this->preview([...$body, 'salary' => -5])->assertStatus(422);
        $this->preview([...$body, 'note' => 'See https://evil.example'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
    }

    // ----- the applicant and the caller -----

    public function test_the_preview_follows_the_applicants_language_and_says_when_the_email_is_off(): void
    {
        UserModel::query()->where('id', $this->seekerId)->update(['locale' => 'id']);
        $this->api('PUT', '/me/notification-preferences/application_update/email', ['enabled' => false], $this->seekerToken)->assertNoContent();

        $data = $this->preview($this->interviewBody())->assertOk()->json('data');

        $this->assertSame('id', $data['locale']);
        $this->assertSame('Jadwal wawancara untuk Backend Developer', $data['subject']);
        $this->assertFalse($data['email_enabled']);
        $this->assertStringContainsString('tidak pernah memungut biaya', $data['text']);
    }

    public function test_the_member_needs_the_permission_of_the_action_and_the_job_in_their_scope(): void
    {
        $reader = $this->memberWith(['applications.view'], 'reader@example.test');
        $interviews = $this->memberWith(['applications.view', 'interviews.manage'], 'interviews@example.test');
        $testId = $this->publishedTestId();
        $due = Carbon::now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z');

        $this->preview($this->interviewBody(), $reader['token'])->assertForbidden()->assertJsonPath('permission', 'interviews.manage');
        $this->preview(['scenario' => 'offer_sent', ...$this->offerDetails()], $interviews['token'])->assertForbidden()->assertJsonPath('permission', 'offers.manage');
        $this->preview(['scenario' => 'assessment_invited', 'test_id' => $testId, 'due_at' => $due], $interviews['token'])->assertForbidden()->assertJsonPath('permission', 'assessments.send');

        // A recruiter of another job, another company's admin and the applicant see no such application.
        $this->preview($this->interviewBody(), $this->secondRecruiterToken)->assertNotFound();
        $this->preview($this->interviewBody(), $this->otherAdminToken)->assertNotFound();
        $this->preview($this->interviewBody(), $this->seekerToken)->assertForbidden();
        $this->preview($this->interviewBody(), id: 999_999)->assertNotFound();
    }
}
