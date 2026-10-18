<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/**
 * `docs/design/phase-8-interview-calendar.md` section 6 and `phase-8-recruiter-workday.md` section 5: the link rule on the notes of interviews and offers, the company
 * frame (logo and the fixed line) of the emails, the note in the offer email, and the email that confirms a received application.
 */
final class CandidateMessagesTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        DB::table('company_companies')->where('id', $this->companyId)->update(['website' => 'https://www.maju.example']);
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function applied(?int $job = null): int
    {
        return (int) $this->apply($job ?? $this->job)->assertCreated()->json('data.id');
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function move(int $applicationId, string $stage, array $body): TestResponse
    {
        return $this->api('POST', "/company/applications/{$applicationId}/move", ['stage_id' => $this->stage($stage), ...$body], $this->recruiterToken);
    }

    /** @return list<Email> */
    private function emailsToSeeker(): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();
        $all = array_map(fn ($sent) => $sent->getOriginalMessage(), $transport->messages()->all());

        return array_values(array_filter($all, fn ($email): bool => $email->getTo()[0]->getAddress() === 'siti@example.test'));
    }

    private function emailWithSubject(string $text): ?Email
    {
        foreach (array_reverse($this->emailsToSeeker()) as $email) {
            if (str_contains((string) $email->getSubject(), $text)) {
                return $email;
            }
        }

        return null;
    }

    // ----- the link rule -----

    public function test_an_interview_note_with_a_foreign_link_is_refused_everywhere_it_can_be_written(): void
    {
        $id = $this->applied();

        $this->move($id, 'interview', ['interview' => $this->interviewSchedule(['note' => 'Pay the fee at https://evil.example/pay'])])
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        $this->assertSame(0, DB::table('recruitment_interviews')->count());

        $this->move($id, 'interview', ['interview' => $this->interviewSchedule(['note' => 'Read https://www.maju.example/careers or call 021 555 0100'])])->assertOk();

        $this->api('PATCH', "/company/applications/{$id}/interview", ['note' => 'Mail hr@gmail.com'], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        $this->api('PUT', "/company/applications/{$id}/interview", $this->interviewSchedule(['note' => 'Join at bit.ly/abc']), $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        $this->api('PATCH', "/company/applications/{$id}/interview", ['note' => 'Ask hr@maju.example'], $this->recruiterToken)->assertOk();
    }

    public function test_a_rescheduled_interview_keeps_its_note_without_checking_it_again(): void
    {
        $id = $this->applied();
        $this->move($id, 'interview', ['interview' => $this->interviewSchedule(['note' => 'Read https://www.maju.example/careers'])])->assertOk();

        // The company changed its website later; a new time without a note keeps the old note and is not refused for it.
        DB::table('company_companies')->where('id', $this->companyId)->update(['website' => 'https://other.example']);
        $this->api('PUT', "/company/applications/{$id}/interview", $this->interviewSchedule(['starts_at' => now()->addDays(5)->utc()->format('Y-m-d\TH:i:s\Z')]), $this->recruiterToken)->assertOk();
    }

    public function test_an_offer_note_with_a_foreign_link_is_refused_and_a_good_one_reaches_the_email(): void
    {
        $id = $this->applied();

        $this->move($id, 'offer', ['offer' => $this->offerDetails(['note' => 'Sign at https://evil.example/sign'])])
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        $this->assertSame(0, DB::table('recruitment_offers')->count());

        $this->move($id, 'offer', ['offer' => $this->offerDetails(['note' => 'Welcome aboard, mail hr@maju.example.'])])->assertOk();

        $email = $this->emailWithSubject('Job offer for Backend Developer');
        $this->assertNotNull($email);
        $this->assertStringContainsString('Note: Welcome aboard, mail hr@maju.example.', (string) $email->getTextBody());
    }

    // ----- the frame of the emails -----

    public function test_company_emails_carry_the_company_name_or_logo_and_the_fixed_line(): void
    {
        $id = $this->applied();
        $this->move($id, 'interview', ['interview' => $this->interviewSchedule()])->assertOk();

        $email = $this->emailWithSubject('Interview scheduled for Backend Developer');
        $this->assertNotNull($email);
        $this->assertStringContainsString('<strong>PT-MAJU</strong>', (string) $email->getHtmlBody());
        $this->assertStringNotContainsString('<img', (string) $email->getHtmlBody());
        $this->assertStringContainsString('never charge a fee in a recruitment process', (string) $email->getHtmlBody());
        $this->assertStringContainsString('never charge a fee in a recruitment process', (string) $email->getTextBody());

        DB::table('company_companies')->where('id', $this->companyId)->update(['logo_path' => 'company/logos/maju.png']);
        $this->api('PUT', "/company/applications/{$id}/interview", $this->interviewSchedule(['starts_at' => now()->addDays(6)->utc()->format('Y-m-d\TH:i:s\Z')]), $this->recruiterToken)->assertOk();

        $html = (string) $this->emailWithSubject('was rescheduled')?->getHtmlBody();
        $this->assertMatchesRegularExpression('~<img src="[^"]*/api/v1/companies/pt-maju/logo\?v=[^"]+" alt="PT-MAJU"~', $html);
        $this->assertStringNotContainsString('<strong>PT-MAJU</strong>', $html);
    }

    // ----- the receipt of an application -----

    public function test_applying_sends_the_receipt_email_with_the_company_message_and_the_link(): void
    {
        config(['app.application_url' => 'https://app.example/applications/{application_id}']);
        $this->api('PUT', '/company/message-defaults', ['applied' => 'We reply within 7 days.'], $this->adminToken)->assertOk();

        $id = $this->applied();

        $email = $this->emailWithSubject('We received your application for Backend Developer');
        $this->assertNotNull($email);
        $text = (string) $email->getTextBody();
        $this->assertStringContainsString('PT-MAJU on 2026-10-05', $text);
        $this->assertStringContainsString('A message from the company: We reply within 7 days.', $text);
        $this->assertStringContainsString("See your application: https://app.example/applications/{$id}", $text);
        $this->assertStringContainsString('<strong>PT-MAJU</strong>', (string) $email->getHtmlBody());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->seekerId)->where('type', 'like', 'application.%')->count());
    }

    public function test_the_receipt_has_no_link_or_message_when_there_are_none_and_follows_the_preference(): void
    {
        config(['app.application_url' => null]);
        $this->applied();

        $text = (string) $this->emailWithSubject('We received your application')?->getTextBody();
        $this->assertStringNotContainsString('See your application', $text);
        $this->assertStringNotContainsString('A message from the company', $text);

        $this->api('PUT', '/me/notification-preferences/application_update/email', ['enabled' => false], $this->seekerToken)->assertNoContent();
        $second = $this->insertJob('Data Analyst', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applied($second);

        $this->assertNull($this->emailWithSubject('Data Analyst'));
    }
}
