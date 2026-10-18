<?php

namespace Tests\Feature\Assessment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-interview-calendar.md` section 6: the note for the candidate on a test invitation, its link rule and its place in the email and the candidate's page. */
final class InvitationNoteTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $job;

    private int $applicationId;

    private int $testId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        DB::table('company_companies')->where('id', $this->companyId)->update(['website' => 'https://www.maju.example']);
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');

        $id = $this->newTest('company', ['title' => 'Logic and numbers']);
        $this->sectionWithQuestion('company', $id);
        $this->company('POST', "tests/{$id}/publish")->assertOk();
        $this->testId = $id;
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function invitation(array $overrides = []): array
    {
        return ['test_id' => $this->testId, 'due_at' => $this->at(now()->addDays(3)), ...$overrides];
    }

    private function at(Carbon $time): string
    {
        return $time->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function send(array $overrides = []): TestResponse
    {
        return $this->company('PUT', "applications/{$this->applicationId}/invitation", $this->invitation($overrides), $this->recruiterToken);
    }

    /** The team sends a test only in an assessment stage: the move puts the application there with a first test, and the tests send a note by replacing it. */
    private function inAssessmentStage(): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'assessment' => $this->invitation()], $this->recruiterToken)->assertOk();
    }

    private function inviteEmail(): ?Email
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();

        foreach (array_reverse($transport->messages()->all()) as $sent) {
            $email = $sent->getOriginalMessage();

            if ($email instanceof Email && str_contains((string) $email->getSubject(), 'A test for your application')) {
                return $email;
            }
        }

        return null;
    }

    public function test_the_note_is_saved_shown_to_both_sides_and_put_in_the_email(): void
    {
        $this->inAssessmentStage();

        $id = (int) $this->send(['note' => '  Use a quiet room and a laptop.  '])->assertOk()->assertJsonPath('data.note', 'Use a quiet room and a laptop.')->json('data.id');

        $this->assertSame('Use a quiet room and a laptop.', DB::table('assessment_invitations')->where('id', $id)->value('note'));
        $this->company('GET', "invitations/{$id}", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.note', 'Use a quiet room and a laptop.');
        $this->api('GET', "/me/assessment/invitations/{$id}", token: $this->seekerToken)->assertOk()->assertJsonPath('data.note', 'Use a quiet room and a laptop.');
        $this->api('GET', '/me/assessment/invitations', token: $this->seekerToken)->assertOk()->assertJsonPath('data.0.note', 'Use a quiet room and a laptop.');

        $text = (string) $this->inviteEmail()?->getTextBody();
        $this->assertStringContainsString('Note: Use a quiet room and a laptop.', $text);
        $this->assertStringContainsString('never charge a fee in a recruitment process', $text);
    }

    public function test_the_note_is_optional_and_a_blank_one_is_no_note(): void
    {
        $this->inAssessmentStage();

        $this->send()->assertOk()->assertJsonPath('data.note', null);
        $this->assertStringNotContainsString('Note:', (string) $this->inviteEmail()?->getTextBody());

        $this->send(['note' => '   '])->assertOk()->assertJsonPath('data.note', null);
    }

    public function test_the_note_has_at_most_a_thousand_characters_and_no_foreign_link(): void
    {
        $this->inAssessmentStage();

        $this->send(['note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonValidationErrors(['note']);

        foreach (['Open https://evil.example/test', 'Mail hr@gmail.com', 'Join bit.ly/x'] as $note) {
            $this->send(['note' => $note])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        }

        $this->assertSame(1, DB::table('assessment_invitations')->where('status', 'invited')->count());
        $this->assertNull(DB::table('assessment_invitations')->where('status', 'invited')->value('note'));
        $this->send(['note' => str_repeat('a', 1000)])->assertOk();
        $this->send(['note' => 'Read https://www.maju.example/test-guide'])->assertOk();
    }

    public function test_a_move_to_an_assessment_stage_carries_the_note_and_refuses_a_foreign_link(): void
    {
        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'assessment')->where('is_default', 1)->value('id');
        $move = fn (string $note): TestResponse => $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'assessment' => $this->invitation(['note' => $note])], $this->recruiterToken);

        $move('Go to https://evil.example')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        $this->assertSame('applied', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'));

        $move('Bring your ID.')->assertOk();
        $this->assertSame('Bring your ID.', DB::table('assessment_invitations')->value('note'));
    }
}
