<?php

namespace Tests\Feature\JobInvitation;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\JobInvitation\Infrastructure\Mail\EmailInvitationMail;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** Invitations by email: a person the company met outside the platform (`docs/design/phase-8-manual-candidates.md`). */
final class EmailInvitationTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->app->make(SettingsStore::class)->setGlobal('invitations.email_enabled', true, null);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(array $body = [], ?int $job = null, ?string $token = null, ?UploadedFile $cv = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $payload = ['name' => 'Dewi Lestari', 'email' => 'dewi@example.test', 'channel' => 'walk_in', ...$body];

        if ($cv !== null) {
            $payload['cv'] = $cv;
        }

        return $this->withToken($token ?? $this->adminToken)->post('/api/v1/company/jobs/'.($job ?? $this->job).'/email-invitations', $payload, ['Accept' => 'application/json']);
    }

    private function cv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('Dewi CV.pdf', $this->pdf());
    }

    /** The token of the link in the email that was queued last. */
    private function tokenFromMail(): string
    {
        $token = null;

        Mail::assertQueued(EmailInvitationMail::class, function (EmailInvitationMail $mail) use (&$token): bool {
            $token = $mail->render();

            return true;
        });

        preg_match('~/undangan-lamaran/([0-9a-f]{48})~', (string) $token, $match);

        return $match[1] ?? throw new \LogicException('No token in the email.');
    }

    /** Moves the last sending back by more than a day (the clock is not moved: the sign-in tokens of the test would expire). */
    private function sentADayAgo(int $id): void
    {
        DB::table('jobinvitation_email_invitations')->where('id', $id)->update(['last_sent_at' => now()->subDay()->subMinute()]);
    }

    // ----- the switch and access -----

    public function test_the_feature_is_off_until_it_is_switched_on(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('invitations.email_enabled', false, null);

        $this->send()->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:feature-disabled');
        $this->api('GET', "/company/jobs/{$this->job}/email-invitations")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/email-invitations/'.str_repeat('a', 48))->assertNotFound();
    }

    public function test_only_members_with_the_permission_send_and_only_for_jobs_they_see(): void
    {
        $this->send(token: $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->send(token: $this->secondRecruiterToken)->assertNotFound();
        $this->send(token: $this->otherAdminToken)->assertNotFound();
        $this->send(job: 999_999)->assertNotFound();
        $this->assertSame(0, DB::table('jobinvitation_email_invitations')->count());

        $this->send(token: $this->recruiterToken)->assertCreated();
    }

    // ----- inviting -----

    public function test_an_invitation_is_listed_for_the_team_and_emailed_with_a_link(): void
    {
        Mail::fake();

        $response = $this->send(['note' => '  Nice to meet you.  ', 'channel' => 'job_fair'])->assertCreated();
        $response->assertJsonPath('data.name', 'Dewi Lestari')
            ->assertJsonPath('data.email', 'dewi@example.test')
            ->assertJsonPath('data.channel', 'job_fair')
            ->assertJsonPath('data.note', 'Nice to meet you.')
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.has_cv', false)
            ->assertJsonPath('data.invited_by', ['id' => $this->adminId, 'name' => 'Andi Admin']);

        Mail::assertQueued(EmailInvitationMail::class, fn (EmailInvitationMail $mail): bool => $mail->hasTo('dewi@example.test'));
        $this->assertNotSame('', $this->tokenFromMail());

        $this->api('GET', "/company/jobs/{$this->job}/email-invitations")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $response->json('data.id'));
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'invitation.email_sent')->count());
    }

    public function test_the_team_sees_who_may_not_be_tested_and_the_same_person_is_invited_once(): void
    {
        $this->send()->assertCreated();
        $this->send(['email' => 'DEWI@example.test'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-invited');
        $this->assertSame(1, DB::table('jobinvitation_email_invitations')->count());

        DB::table('platform_email_suppressions')->insert(['email' => 'blocked@example.test', 'reason' => 'hard_bounce', 'created_at' => now()]);
        $this->send(['email' => 'blocked@example.test'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:email-suppressed');
    }

    public function test_the_job_must_be_live_and_the_fields_valid(): void
    {
        $draft = $this->insertJob('Draft', 'draft', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);

        $this->send(job: $draft)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:job-not-live');
        $this->send(['name' => ''])->assertStatus(422);
        $this->send(['email' => 'not-an-email'])->assertStatus(422);
        $this->send(['channel' => 'carrier_pigeon'])->assertStatus(422);
        $this->send(['note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:note-too-long');
    }

    public function test_a_cv_is_checked_by_its_content_and_read_with_an_audit_entry(): void
    {
        Mail::fake();
        Storage::fake();

        $this->send(cv: UploadedFile::fake()->createWithContent('cv.pdf', 'plain text'))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-file');
        $this->send(cv: UploadedFile::fake()->createWithContent('cv.pdf', $this->pdf().'/JavaScript'))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-file');

        $id = (int) $this->send(cv: $this->cv())->assertCreated()->assertJsonPath('data.has_cv', true)->json('data.id');

        $this->api('GET', "/company/email-invitations/{$id}/cv", token: $this->adminToken)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'invitation.cv_viewed')->count());
        $this->api('GET', "/company/email-invitations/{$id}/cv", token: $this->otherAdminToken)->assertStatus(404);
    }

    public function test_the_daily_cap_counts_these_invitations_too(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('invitations.max_per_day', 1, null);

        $this->send()->assertCreated();
        $this->send(['email' => 'second@example.test'])->assertStatus(429)->assertJsonPath('type', 'urn:onesis:error:invitation-limit');
    }

    // ----- the person's side -----

    public function test_the_link_shows_the_company_and_the_job_and_nothing_of_what_the_company_holds(): void
    {
        Mail::fake();
        $this->send(['note' => 'Hello'])->assertCreated();
        $token = $this->tokenFromMail();

        $this->app['auth']->forgetGuards();
        $response = $this->flushHeaders()->getJson("/api/v1/email-invitations/{$token}")->assertOk();
        $response->assertJsonPath('data.job_title', 'Backend Developer')->assertJsonPath('data.note', 'Hello')->assertJsonPath('data.open', true)->assertJsonPath('data.job_live', true);
        $this->assertArrayNotHasKey('email', $response->json('data'));
        $this->assertArrayNotHasKey('name', $response->json('data'));

        $this->assertSame('opened', DB::table('jobinvitation_email_invitations')->value('status'));
        $this->flushHeaders()->getJson('/api/v1/email-invitations/'.str_repeat('0', 48))->assertNotFound();
    }

    public function test_the_person_refuses_and_the_data_is_deleted_at_once(): void
    {
        Mail::fake();
        Storage::fake();
        $this->send(cv: $this->cv())->assertCreated();
        $token = $this->tokenFromMail();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->postJson("/api/v1/email-invitations/{$token}/decline")->assertNoContent();

        $row = DB::table('jobinvitation_email_invitations')->first();
        $this->assertSame('declined', $row->status);
        $this->assertNull($row->name);
        $this->assertNull($row->email);
        $this->assertNull($row->cv_path);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->postJson("/api/v1/email-invitations/{$token}/decline")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:invitation-closed');
    }

    public function test_a_verified_job_seeker_claims_takes_the_cv_and_applies_with_the_manual_source(): void
    {
        Mail::fake();
        Storage::fake();
        $this->send(cv: $this->cv())->assertCreated();
        $token = $this->tokenFromMail();

        $id = $this->seeker('someone.else@example.test', name: 'Dewi L');
        $seekerToken = $this->tokenFor($id);
        $this->completeProfile($seekerToken, withResume: false);

        // A company member cannot claim, and an unverified account cannot either.
        $this->api('POST', "/me/email-invitations/{$token}/claim", ['agree' => true], $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        $unverified = $this->seeker('unverified@example.test', null);
        $this->api('POST', "/me/email-invitations/{$token}/claim", ['agree' => true], $this->tokenFor($unverified))->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:email-not-verified');
        $this->api('POST', "/me/email-invitations/{$token}/claim", [], $seekerToken)->assertStatus(422);

        $this->api('POST', "/me/email-invitations/{$token}/claim", ['agree' => true], $seekerToken)->assertOk()
            ->assertJsonPath('data.job_id', $this->job)->assertJsonPath('data.has_cv', true);
        // Claiming again with the same account gives the same answer; another account is refused.
        $this->api('POST', "/me/email-invitations/{$token}/claim", ['agree' => true], $seekerToken)->assertOk();
        $this->api('POST', "/me/email-invitations/{$token}/claim", ['agree' => true], $this->tokenFor($this->seeker('third@example.test')))->assertStatus(409);

        $resumeId = (int) $this->api('POST', "/me/email-invitations/{$token}/use-cv", token: $seekerToken)->assertCreated()->json('data.resume_id');
        $this->assertNull(DB::table('jobinvitation_email_invitations')->value('cv_path'));

        $this->apply($this->job, ['resume_id' => $resumeId], $seekerToken)->assertCreated();
        $this->assertSame('manual', DB::table('recruitment_applications')->where('job_id', $this->job)->value('source'));
    }

    public function test_the_person_may_discard_the_cv_the_company_sent(): void
    {
        Mail::fake();
        Storage::fake();
        $this->send(cv: $this->cv())->assertCreated();
        $token = $this->tokenFromMail();
        $seekerToken = $this->tokenFor($this->seeker('dewi.new@example.test'));

        $this->api('POST', "/me/email-invitations/{$token}/claim", ['agree' => true], $seekerToken)->assertOk();
        $this->api('POST', "/me/email-invitations/{$token}/discard-cv", token: $seekerToken)->assertNoContent();
        $this->assertNull(DB::table('jobinvitation_email_invitations')->value('cv_path'));
        $this->api('POST', "/me/email-invitations/{$token}/use-cv", token: $seekerToken)->assertNotFound();
    }

    // ----- resend, cancel and purge -----

    public function test_a_resend_once_a_day_at_most_three_times_with_a_new_link(): void
    {
        Mail::fake();
        $id = (int) $this->send()->assertCreated()->json('data.id');
        $first = $this->tokenFromMail();

        $this->api('POST', "/company/email-invitations/{$id}/resend")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:resend-limit');

        foreach ([1, 2, 3] as $count) {
            $this->sentADayAgo($id);
            $this->api('POST', "/company/email-invitations/{$id}/resend")->assertOk()->assertJsonPath('data.resent_count', $count);
        }

        $this->sentADayAgo($id);
        $this->api('POST', "/company/email-invitations/{$id}/resend")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:resend-limit');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson("/api/v1/email-invitations/{$first}")->assertNotFound();
    }

    public function test_cancelling_deletes_the_data_and_the_row_stays_for_the_counts(): void
    {
        Storage::fake();
        $id = (int) $this->send(cv: $this->cv())->assertCreated()->json('data.id');

        $this->api('POST', "/company/email-invitations/{$id}/cancel", token: $this->otherAdminToken)->assertNotFound();
        $this->api('POST', "/company/email-invitations/{$id}/cancel")->assertNoContent();

        $row = DB::table('jobinvitation_email_invitations')->first();
        $this->assertSame('cancelled', $row->status);
        $this->assertNull($row->email);
        $this->assertNull($row->cv_path);
        $this->api('POST', "/company/email-invitations/{$id}/cancel")->assertStatus(409);
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'invitation.email_cancelled')->count());
    }

    public function test_the_purge_expires_open_invitations_after_thirty_days(): void
    {
        Storage::fake();
        $this->send(cv: $this->cv())->assertCreated();
        $this->send(['email' => 'recent@example.test'])->assertCreated();
        DB::table('jobinvitation_email_invitations')->where('email', 'dewi@example.test')->update(['created_at' => now()->subDays(31)]);

        $this->artisan('jobinvitation:purge-email-invitations')->assertSuccessful();

        $old = DB::table('jobinvitation_email_invitations')->where('status', 'expired')->first();
        $this->assertNotNull($old);
        $this->assertNull($old->email);
        $this->assertNull($old->cv_path);
        $this->assertSame('sent', DB::table('jobinvitation_email_invitations')->where('email', 'recent@example.test')->value('status'));
    }
}
