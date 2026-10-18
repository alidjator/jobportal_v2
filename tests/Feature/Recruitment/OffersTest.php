<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Recruitment\Application\EraseApplicantData;
use Src\Recruitment\Application\ProcessOfferDeadlines;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class OffersTest extends FeatureTestCase
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

    private function toOfferStage(?array $offer = null): TestResponse
    {
        $body = ['stage_id' => $this->stage('offer')];

        if ($offer !== null) {
            $body['offer'] = $offer;
        }

        return $this->api('POST', "/company/applications/{$this->applicationId}/move", $body, $this->recruiterToken);
    }

    private function send(array $overrides = [], ?string $token = null): TestResponse
    {
        return $this->api('PUT', "/company/applications/{$this->applicationId}/offer", $this->offerDetails($overrides), $token ?? $this->recruiterToken);
    }

    private function mine(string $action, array $body = [], string $method = 'POST'): TestResponse
    {
        return $this->api($method, "/me/applications/{$this->applicationId}/offer".($action === '' ? '' : "/{$action}"), $body, $this->seekerToken);
    }

    private function applicationType(): string
    {
        return (string) DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type');
    }

    private function notificationTypes(int $userId): array
    {
        $types = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', 'like', 'offer.%')->pluck('type')->all();
        sort($types);

        return $types;
    }

    /** @return list<Email> */
    private function sentEmails(): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();

        return array_values(array_map(fn ($sent) => $sent->getOriginalMessage(), $transport->messages()->all()));
    }

    // ----- moving to an offer stage -----

    public function test_a_move_to_an_offer_stage_needs_an_offer_and_saves_it_with_the_move(): void
    {
        $this->toOfferStage()->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:offer-required');
        $this->assertSame('applied', $this->applicationType());

        $this->toOfferStage($this->offerDetails())->assertOk();
        $this->assertSame('offer', $this->applicationType());
        $this->assertSame('pending', DB::table('recruitment_offers')->value('status'));
        $this->assertSame(12_000_000, (int) DB::table('recruitment_offers')->value('salary'));
        $this->assertContains('offer.sent', $this->notificationTypes($this->seekerId));
    }

    public function test_an_offer_for_another_stage_is_refused(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('screening'), 'offer' => $this->offerDetails()], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer-input');
    }

    public function test_the_position_defaults_to_the_job_title_and_the_values_are_checked(): void
    {
        $this->toOfferStage($this->offerDetails(['position' => null]))->assertOk();
        $this->assertSame('Backend Developer', DB::table('recruitment_offers')->value('position'));

        $this->send(['salary' => 0])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-offer');
        $this->send(['start_date' => now()->subDays(2)->format('Y-m-d')])->assertStatus(422);
        $this->send(['expires_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z')])->assertStatus(422);
        $this->send(['expires_at' => now()->addDays(31)->utc()->format('Y-m-d\TH:i:s\Z')])->assertStatus(422);
        $this->send(['note' => str_repeat('a', 2001)])->assertStatus(422);
        $this->send(['salary' => 'much'])->assertStatus(422);
        $this->assertSame(1, DB::table('recruitment_offers')->count());
    }

    // ----- sending and revising -----

    public function test_sending_needs_the_offer_stage_and_a_revision_replaces_the_pending_offer(): void
    {
        $this->send()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:not-in-offer-stage');

        $this->toOfferStage($this->offerDetails())->assertOk();
        $this->send(['salary' => 15_000_000])->assertOk()->assertJsonPath('data.salary', 15_000_000)->assertJsonPath('data.status', 'pending');

        $this->assertSame(['pending', 'withdrawn'], DB::table('recruitment_offers')->orderByDesc('id')->pluck('status')->all());
        $this->assertContains('offer.revised', $this->notificationTypes($this->seekerId));
        $this->api('GET', "/company/applications/{$this->applicationId}/offers", token: $this->recruiterToken)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.status', 'pending');
        $this->mine('', method: 'GET')->assertOk()->assertJsonPath('data.salary', 15_000_000);
    }

    public function test_a_first_offer_sent_after_moving_with_an_existing_one_answers_201(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();
        $this->api('POST', "/company/applications/{$this->applicationId}/offer/withdraw", token: $this->recruiterToken)->assertOk();

        $this->send()->assertCreated();
    }

    public function test_the_team_withdraws_the_offer_and_the_application_stays_in_its_stage(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();
        $this->api('POST', "/company/applications/{$this->applicationId}/offer/withdraw", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.status', 'withdrawn');
        $this->api('POST', "/company/applications/{$this->applicationId}/offer/withdraw", token: $this->recruiterToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-active-offer');

        $this->assertSame('offer', $this->applicationType());
        $this->assertContains('offer.withdrawn', $this->notificationTypes($this->seekerId));
        $this->mine('', method: 'GET')->assertOk()->assertJsonPath('data', null);
    }

    public function test_only_the_team_that_sees_the_job_manages_offers(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();

        $this->send(token: $this->otherAdminToken)->assertStatus(404);
        $this->api('GET', "/company/applications/{$this->applicationId}/offers", token: $this->otherAdminToken)->assertStatus(404);
        $this->api('PUT', "/company/applications/{$this->applicationId}/offer", $this->offerDetails(), $this->seekerToken)->assertStatus(403);
    }

    public function test_moving_out_of_the_offer_stage_withdraws_the_offer(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertOk();

        $this->assertSame('withdrawn', DB::table('recruitment_offers')->value('status'));
        $this->assertContains('offer.withdrawn', $this->notificationTypes($this->seekerId));
    }

    // ----- the job seeker answers -----

    public function test_accepting_hires_the_applicant_and_tells_the_sender(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();

        $this->mine('accept')->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->assertSame('hired', $this->applicationType());
        $this->assertSame(0, DB::table('recruitment_offers')->whereNotNull('active_key')->count());
        $this->assertSame(['offer.accepted'], $this->notificationTypes($this->recruiterId));
        $this->assertSame('applicant', DB::table('recruitment_application_stage_history')->where('application_id', $this->applicationId)->orderByDesc('id')->value('actor_kind'));
        $this->mine('accept')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-active-offer');
    }

    public function test_declining_withdraws_the_application_with_the_note_and_frees_the_job(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();

        $this->mine('decline', ['note' => 'I took another job.'])->assertOk()->assertJsonPath('data.status', 'declined')->assertJsonPath('data.answer_note', 'I took another job.');

        $this->assertSame('withdrawn', $this->applicationType());
        $this->assertSame('I took another job.', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('withdrawn_note'));
        $this->assertSame(['offer.declined'], $this->notificationTypes($this->recruiterId));
        $this->apply($this->job)->assertCreated();
    }

    public function test_a_long_decline_note_is_refused_and_a_blank_one_is_dropped(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();

        $this->mine('decline', ['note' => str_repeat('a', 1001)])->assertStatus(422);
        $this->assertSame('offer', $this->applicationType());
        $this->mine('decline', ['note' => '  '])->assertOk()->assertJsonPath('data.answer_note', null);
    }

    public function test_nothing_to_answer_without_a_pending_offer(): void
    {
        $this->mine('accept')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:no-active-offer');
        $this->mine('decline')->assertStatus(409);
    }

    public function test_only_the_applicant_answers_and_only_their_own_application(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();

        $this->api('POST', "/me/applications/{$this->applicationId}/offer/accept", token: $this->recruiterToken)->assertStatus(403);
        $other = $this->tokenFor($this->seeker('lain@example.test'));
        $this->api('POST', "/me/applications/{$this->applicationId}/offer/accept", token: $other)->assertStatus(404);
        $this->api('GET', "/me/applications/{$this->applicationId}/offer", token: $other)->assertStatus(404);
    }

    // ----- the end of the application -----

    public function test_rejecting_or_withdrawing_withdraws_the_pending_offer(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();

        $this->assertSame('withdrawn', DB::table('recruitment_offers')->value('status'));
        $this->assertContains('offer.withdrawn', $this->notificationTypes($this->seekerId));
    }

    public function test_withdrawing_the_application_withdraws_the_pending_offer(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();
        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", token: $this->seekerToken)->assertNoContent();

        $this->assertSame('withdrawn', DB::table('recruitment_offers')->value('status'));
    }

    // ----- the deadline -----

    public function test_an_offer_past_its_deadline_cannot_be_answered_and_the_team_may_send_a_new_one(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();

        // The deadline passes (the clock is not moved: the access tokens would expire with it).
        DB::table('recruitment_offers')->update(['expires_at' => now()->subHour()->utc()->format('Y-m-d H:i:s')]);
        $this->mine('', method: 'GET')->assertOk()->assertJsonPath('data.status', 'expired');
        $this->mine('accept')->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:offer-expired');
        $this->assertSame('offer', $this->applicationType());

        $this->api('PUT', "/company/applications/{$this->applicationId}/offer", $this->offerDetails([
            'expires_at' => now()->addDays(5)->utc()->format('Y-m-d\TH:i:s\Z'),
        ]), $this->recruiterToken)->assertOk();
        $this->mine('accept')->assertOk();
    }

    public function test_the_hourly_run_expires_and_warns_once(): void
    {
        $this->toOfferStage($this->offerDetails(['expires_at' => now()->addDays(2)->utc()->format('Y-m-d\TH:i:s\Z')]))->assertOk();
        $deadlines = $this->app->make(ProcessOfferDeadlines::class);

        $this->assertSame(['expired' => 0, 'reminded' => 0], $deadlines->execute());

        Carbon::setTestNow(now()->addDays(1)->addHours(2));
        $this->assertSame(['expired' => 0, 'reminded' => 1], $deadlines->execute());
        $this->assertSame(['expired' => 0, 'reminded' => 0], $deadlines->execute());
        $this->assertContains('offer.expiring', $this->notificationTypes($this->seekerId));

        Carbon::setTestNow(now()->addDays(2));
        $this->assertSame(['expired' => 1, 'reminded' => 0], $deadlines->execute());
        $this->assertSame('expired', DB::table('recruitment_offers')->value('status'));
        $this->assertSame(0, DB::table('recruitment_offers')->whereNotNull('active_key')->count());
    }

    public function test_the_deadline_command_runs(): void
    {
        $this->artisan('recruitment:process-offer-deadlines')->expectsOutput('Expired 0 and reminded 0 offer(s).')->assertSuccessful();
    }

    // ----- emails and erasure -----

    public function test_the_job_seeker_is_emailed_about_the_offer_and_follows_the_preference(): void
    {
        $this->toOfferStage($this->offerDetails(['salary' => 12_500_000]))->assertOk();

        $offer = collect($this->sentEmails())->first(fn ($email): bool => str_contains($email->getSubject() ?? '', 'Job offer for'));
        $this->assertNotNull($offer);
        $this->assertStringContainsString('Rp 12.500.000', (string) $offer->getTextBody());
        $this->assertStringContainsString('WIB', (string) $offer->getTextBody());

        $this->api('PUT', '/me/notification-preferences/application_update/email', ['enabled' => false], $this->seekerToken)->assertNoContent();
        $before = count($this->sentEmails());
        $this->send(['salary' => 13_000_000])->assertOk();
        $this->assertCount($before, $this->sentEmails());
        $this->assertContains('offer.revised', $this->notificationTypes($this->seekerId));
    }

    public function test_erasing_the_applicant_deletes_their_offers(): void
    {
        $this->toOfferStage($this->offerDetails())->assertOk();

        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);

        $this->assertSame(0, DB::table('recruitment_offers')->count());
    }
}
