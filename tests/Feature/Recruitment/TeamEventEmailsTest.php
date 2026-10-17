<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-recruiter-workday.md` section 3: the emails to the team about an interview answer, an offer answer and a new application, by the members' preferences. */
final class TeamEventEmailsTest extends FeatureTestCase
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
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function move(string $type, array $details): TestResponse
    {
        return $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage($type), $type === 'offer' ? 'offer' : 'interview' => $details], $this->recruiterToken);
    }

    private function answer(string $what, string $action, array $body = []): TestResponse
    {
        return $this->api('POST', "/me/applications/{$this->applicationId}/{$what}/{$action}", $body, $this->seekerToken);
    }

    private function preference(string $key, bool $enabled, ?string $token = null): void
    {
        $this->api('PUT', "/me/notification-preferences/{$key}/email", ['enabled' => $enabled], $token ?? $this->recruiterToken)->assertNoContent();
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

    public function test_the_member_who_sent_the_offer_is_emailed_when_it_is_accepted_or_declined(): void
    {
        $this->move('offer', $this->offerDetails())->assertNoContent();

        $this->answer('offer', 'decline')->assertOk();

        $this->assertContains('The candidate declined the offer for Backend Developer', $this->subjectsTo('rina@example.test'));
    }

    public function test_the_offer_email_follows_the_preference(): void
    {
        $this->preference('team_offer_answers', false);
        $this->move('offer', $this->offerDetails())->assertNoContent();

        $this->answer('offer', 'accept')->assertOk();

        $this->assertSame([], array_filter($this->subjectsTo('rina@example.test'), static fn (string $subject): bool => str_contains($subject, 'The candidate')));
    }

    public function test_the_interviewer_is_emailed_when_the_candidate_confirms_and_the_email_holds_no_name(): void
    {
        $this->move('interview', $this->interviewSchedule())->assertNoContent();

        $this->answer('interview', 'confirm')->assertOk();

        $subjects = $this->subjectsTo('rina@example.test');
        $this->assertContains('The candidate confirmed the interview for Backend Developer', $subjects);
        $this->assertSame([], array_filter($subjects, static fn (string $subject): bool => str_contains($subject, 'Siti')));
    }

    public function test_the_interview_email_follows_the_preference(): void
    {
        $this->preference('team_interview_changes', false);
        $this->move('interview', $this->interviewSchedule())->assertNoContent();

        $this->answer('interview', 'confirm')->assertOk();

        $this->assertSame([], array_filter($this->subjectsTo('rina@example.test'), static fn (string $subject): bool => str_contains($subject, 'The candidate')));
    }

    public function test_a_new_application_is_emailed_only_to_the_members_who_switched_it_on(): void
    {
        $this->preference('team_new_applications', true);
        $token = $this->tokenFor($this->seeker('budi.pelamar@example.test', name: 'Budi Pelamar'));

        $this->apply($this->job, ['resume_id' => $this->completeProfile($token)], $token)->assertCreated();

        $this->assertContains('New application for Backend Developer', $this->subjectsTo('rina@example.test'));
        $this->assertSame([], array_filter($this->subjectsTo('sari@example.test'), static fn (string $subject): bool => str_contains($subject, 'New application')));
    }

    public function test_a_new_application_sends_no_email_by_default(): void
    {
        $this->assertSame([], array_filter($this->subjectsTo('rina@example.test'), static fn (string $subject): bool => str_contains($subject, 'New application')));
    }
}
