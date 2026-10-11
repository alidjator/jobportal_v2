<?php

namespace Tests\Feature\JobInvitation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\JobInvitation\Infrastructure\Mail\InvitationMail;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class InvitationTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    /** @var array{id: int, slug: string, token: string, resume: int} */
    private array $ana;

    private int $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->ana = $this->candidate('ana@example.test', 'Ana Putri');
        $this->job = $this->openJob();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /**
     * A job seeker who can be found: a complete profile that is switched on for search.
     *
     * @return array{id: int, slug: string, token: string, resume: int}
     */
    private function candidate(string $email, string $name): array
    {
        $id = $this->seeker($email, name: $name);
        $token = $this->tokenFor($id);
        $resume = $this->completeProfile($token);
        DB::table('jobseeker_profiles')->where('user_id', $id)->update(['is_discoverable' => true]);

        return ['id' => $id, 'slug' => (string) DB::table('jobseeker_profiles')->where('user_id', $id)->value('slug'), 'token' => $token, 'resume' => $resume];
    }

    /** A live job assigned to the first recruiter. */
    private function openJob(): int
    {
        return $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
    }

    /**
     * @param  array{slug: string}  $candidate
     * @param  array<string, mixed>  $body
     */
    private function invite(array $candidate, ?int $job = null, ?string $token = null, array $body = []): TestResponse
    {
        return $this->api('POST', '/company/jobs/'.($job ?? $this->job).'/invitations', ['candidate' => $candidate['slug'], ...$body], $token ?? $this->adminToken);
    }

    /**
     * @param  array{slug: string}  $candidate
     * @param  array<string, mixed>  $body
     */
    private function invitationId(array $candidate, ?int $job = null, ?string $token = null, array $body = []): int
    {
        return (int) $this->invite($candidate, $job, $token, $body)->assertCreated()->json('data.id');
    }

    /** @return list<array<string, mixed>> */
    private function notes(int $userId, string $type): array
    {
        return array_map(
            fn (object $row): array => json_decode((string) $row->data, true),
            DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->where('type', $type)->orderBy('created_at')->orderBy('id')->get()->all(),
        );
    }

    // ----- access -----

    public function test_only_members_of_an_active_company_invite_and_only_job_seekers_answer(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/company/jobs/'.$this->job.'/invitations')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/me/invitations')->assertUnauthorized();

        $this->invite($this->ana, token: $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
        $this->api('GET', "/company/jobs/{$this->job}/invitations", token: $this->seekerToken)->assertForbidden();

        $id = $this->invitationId($this->ana);
        foreach ([['GET', '/me/invitations'], ['GET', "/me/invitations/{$id}"], ['POST', "/me/invitations/{$id}/decline"]] as [$method, $uri]) {
            $this->api($method, $uri, token: $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:job-seeker-only');
        }

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);
        $this->invite($this->ana, token: $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
        $this->api('GET', "/company/jobs/{$this->job}/invitations", token: $this->recruiterToken)->assertForbidden();
    }

    public function test_a_member_invites_only_to_jobs_they_may_see(): void
    {
        $this->invite($this->ana, token: $this->recruiterToken)->assertCreated();

        $other = $this->candidate('budi.s@example.test', 'Budi Santoso');
        $this->invite($other, token: $this->secondRecruiterToken)->assertNotFound();
        $this->invite($other, token: $this->otherAdminToken)->assertNotFound();
        $this->invite($other, job: 999_999)->assertNotFound();
        $this->api('GET', "/company/jobs/{$this->job}/invitations", token: $this->secondRecruiterToken)->assertNotFound();
        $this->api('GET', "/company/jobs/{$this->job}/invitations", token: $this->otherAdminToken)->assertNotFound();

        // A company admin may invite to a job that is assigned to somebody else.
        $this->invite($other, $this->insertJob('Data Analyst', 'active', [$this->secondRecruiterId], attributes: ['deadline' => '2026-12-31']))->assertCreated();
    }

    // ----- inviting -----

    public function test_an_invitation_tells_the_candidate_in_the_app_and_by_email(): void
    {
        Mail::fake();

        $response = $this->invite($this->ana, body: ['note' => '  We liked your profile.  '])->assertCreated();
        $response->assertJsonPath('data.job_id', $this->job)
            ->assertJsonPath('data.job_title', 'Backend Developer')
            ->assertJsonPath('data.candidate', ['slug' => $this->ana['slug'], 'name' => 'Ana Putri'])
            ->assertJsonPath('data.invited_by', ['id' => $this->adminId, 'name' => 'Andi Admin'])
            ->assertJsonPath('data.note', 'We liked your profile.')
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.expired', false)
            ->assertJsonPath('data.viewed_at', null)
            ->assertJsonPath('data.decline_reason', null);

        $id = (int) $response->json('data.id');
        $this->assertSame(1, DB::table('jobinvitation_invitations')->count());
        $this->assertSame([['invitation_id' => $id, 'job_id' => $this->job, 'company_id' => $this->companyId]], $this->notes($this->ana['id'], 'invitation.received'));
        Mail::assertQueued(InvitationMail::class, 1);
        Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail): bool {
            $mail->assertSeeInText('Backend Developer');
            $mail->assertSeeInText('We liked your profile.');

            return $mail->hasTo('ana@example.test');
        });
    }

    public function test_the_note_is_optional_trimmed_and_limited(): void
    {
        $other = $this->candidate('budi.s@example.test', 'Budi Santoso');
        $third = $this->candidate('citra@example.test', 'Citra Dewi');

        $this->invite($this->ana, body: ['note' => " \n "])->assertCreated()->assertJsonPath('data.note', null);
        $this->invite($other, body: ['note' => str_repeat('é', 1000)])->assertCreated();
        $this->invite($third, body: ['note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:note-too-long');
        $this->invite($third, body: ['note' => str_repeat('a', 4001)])->assertStatus(422);
        $this->invite($third, body: ['candidate' => ''])->assertStatus(422);
        $this->assertSame(2, DB::table('jobinvitation_invitations')->count());
    }

    public function test_the_job_must_be_live_and_the_candidate_findable_and_not_applied(): void
    {
        foreach (['draft', 'pending_review', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $job = $this->insertJob("Job {$status}", $status, [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
            $this->invite($this->ana, $job)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:job-not-live');
        }

        $late = $this->insertJob('Late', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-10-04']);
        $this->invite($this->ana, $late)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:job-not-live');

        $this->invite(['slug' => 'does-not-exist'])->assertNotFound();
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => false]);
        $this->invite($this->ana)->assertNotFound();
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => true]);

        $this->apply($this->job, ['resume_id' => $this->ana['resume']], $this->ana['token'])->assertCreated();
        $this->invite($this->ana)->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:already-applied');
        $this->assertSame(0, DB::table('jobinvitation_invitations')->count());

        // The same candidate may still be invited to another job.
        $this->invite($this->ana, $this->insertJob('Data Analyst', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']))->assertCreated();
    }

    public function test_a_candidate_is_invited_once_per_job_even_after_declining(): void
    {
        $id = $this->invitationId($this->ana);
        $this->invite($this->ana)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:already-invited');

        $this->api('POST', "/me/invitations/{$id}/decline", token: $this->ana['token'])->assertOk();
        $this->invite($this->ana)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:already-invited');
        $this->assertSame(1, DB::table('jobinvitation_invitations')->count());
    }

    public function test_the_company_row_is_locked_before_the_invitation_is_counted_and_written(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->invitationId($this->ana);

        $lock = $this->firstQueryIndex($queries, 'company_companies', 'for update');
        $insert = $this->firstQueryIndex($queries, 'insert into "jobinvitation_invitations"');

        $this->assertNotNull($lock, 'The company row was not locked.');
        $this->assertNotNull($insert);
        $this->assertLessThan($insert, $lock);
    }

    /** @param list<string> $queries */
    private function firstQueryIndex(array $queries, string ...$needles): ?int
    {
        foreach ($queries as $index => $sql) {
            if (count(array_filter($needles, fn (string $needle): bool => str_contains($sql, $needle))) === count($needles)) {
                return $index;
            }
        }

        return null;
    }

    public function test_the_company_has_a_daily_cap_that_a_refused_invitation_does_not_use(): void
    {
        $store = $this->app->make(SettingsStore::class);
        $store->setGlobal('invitations.max_per_day', 2, null);
        $b = $this->candidate('budi.s@example.test', 'Budi Santoso');
        $c = $this->candidate('citra@example.test', 'Citra Dewi');

        $this->invitationId($this->ana);
        $this->invite($this->ana)->assertStatus(409);
        $this->invitationId($b);
        $this->invite($c)->assertStatus(429)->assertJsonPath('type', 'urn:jobportal:error:invitation-limit');
        $this->assertSame(2, DB::table('jobinvitation_invitations')->count());

        // The cap belongs to the company and can be overridden for it.
        $store->setCompanyOverride('invitations.max_per_day', $this->companyId, 3, null);
        $this->invitationId($c);
        $d = $this->candidate('dewi@example.test', 'Dewi Lestari');
        $this->invite($d)->assertStatus(429);

        // A new Jakarta day starts the count again.
        Carbon::setTestNow('2026-10-06 08:00:00');
        $this->invitationId($d);
    }

    // ----- the candidate's side -----

    public function test_the_candidate_lists_and_opens_their_invitations_and_the_first_opening_marks_it_viewed(): void
    {
        $id = $this->invitationId($this->ana, body: ['note' => 'Hello']);
        $this->invitationId($this->candidate('budi.s@example.test', 'Budi Santoso'));

        $list = $this->api('GET', '/me/invitations', token: $this->ana['token'])->assertOk();
        $list->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.job', ['id' => $this->job, 'title' => 'Backend Developer'])
            ->assertJsonPath('data.0.note', 'Hello')
            ->assertJsonPath('data.0.status', 'sent')
            ->assertJsonPath('data.0.company.id', $this->companyId)
            ->assertJsonMissingPath('data.0.invited_by')
            ->assertJsonMissingPath('data.0.candidate');
        $this->assertNotSame('', (string) $list->json('data.0.company.name'));

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->api('GET', "/me/invitations/{$id}", token: $this->ana['token'])->assertOk()->assertJsonPath('data.status', 'viewed')->assertJsonPath('data.viewed_at', '2026-10-05T10:00:00+00:00');
        Carbon::setTestNow('2026-10-05 11:00:00');
        $this->api('GET', "/me/invitations/{$id}", token: $this->ana['token'])->assertOk()->assertJsonPath('data.status', 'viewed')->assertJsonPath('data.viewed_at', '2026-10-05T10:00:00+00:00');

        // Someone else's invitation, and one that does not exist, are the same 404.
        $this->api('GET', "/me/invitations/{$id}", token: $this->seekerToken)->assertNotFound();
        $this->api('GET', '/me/invitations/999999', token: $this->ana['token'])->assertNotFound();
        $this->api('POST', "/me/invitations/{$id}/decline", token: $this->seekerToken)->assertNotFound();
    }

    public function test_the_list_filters_by_status_and_is_newest_first(): void
    {
        $first = $this->invitationId($this->ana);
        Carbon::setTestNow('2026-10-05 10:00:00');
        $secondJob = $this->insertJob('Data Analyst', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $second = $this->invitationId($this->ana, $secondJob);
        $this->api('GET', "/me/invitations/{$first}", token: $this->ana['token'])->assertOk();

        $this->assertSame([$second, $first], $this->api('GET', '/me/invitations', token: $this->ana['token'])->assertOk()->json('data.*.id'));
        $this->assertSame([$first], $this->api('GET', '/me/invitations?filter[status]=viewed', token: $this->ana['token'])->assertOk()->json('data.*.id'));
        $this->assertSame([$second], $this->api('GET', '/me/invitations?filter[status]=sent', token: $this->ana['token'])->assertOk()->json('data.*.id'));
        $this->assertSame([], $this->api('GET', '/me/invitations?filter[status]=declined', token: $this->ana['token'])->assertOk()->json('data'));
        $this->api('GET', '/me/invitations?per_page=1&page=2', token: $this->ana['token'])->assertOk()->assertJsonPath('data.0.id', $first)->assertJsonPath('meta.total', 2);
        $this->api('GET', '/me/invitations?filter[status]=expired', token: $this->ana['token'])->assertStatus(422);
        $this->api('GET', '/me/invitations?filter[other]=1', token: $this->ana['token'])->assertStatus(422);
    }

    public function test_declining_gives_an_optional_reason_that_the_member_who_invited_is_told(): void
    {
        $id = $this->invitationId($this->ana, token: $this->recruiterToken);

        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->api('POST', "/me/invitations/{$id}/decline", ['reason' => '  Not looking for backend work.  '], $this->ana['token'])->assertOk()
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.decline_reason', 'Not looking for backend work.')
            ->assertJsonPath('data.responded_at', '2026-10-05T10:00:00+00:00')
            ->assertJsonPath('data.viewed_at', '2026-10-05T10:00:00+00:00');

        $this->assertSame([['invitation_id' => $id, 'job_id' => $this->job, 'reason' => 'Not looking for backend work.']], $this->notes($this->recruiterId, 'invitation.declined'));
        $this->assertSame([], $this->notes($this->adminId, 'invitation.declined'));

        $this->api('POST', "/me/invitations/{$id}/decline", token: $this->ana['token'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:invitation-closed');
        $this->api('GET', "/company/jobs/{$this->job}/invitations", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.0.status', 'declined')->assertJsonPath('data.0.decline_reason', 'Not looking for backend work.');
    }

    public function test_a_decline_without_a_reason_and_the_limits_of_the_reason(): void
    {
        $id = $this->invitationId($this->ana);
        $b = $this->candidate('budi.s@example.test', 'Budi Santoso');
        $other = $this->invitationId($b);

        $this->api('POST', "/me/invitations/{$id}/decline", ['reason' => str_repeat('a', 501)], $this->ana['token'])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:reason-too-long');
        $this->api('POST', "/me/invitations/{$id}/decline", ['reason' => str_repeat('a', 2001)], $this->ana['token'])->assertStatus(422);
        $this->assertSame('sent', DB::table('jobinvitation_invitations')->where('id', $id)->value('status'));

        $this->api('POST', "/me/invitations/{$id}/decline", ['reason' => str_repeat('é', 500)], $this->ana['token'])->assertOk()->assertJsonPath('data.decline_reason', str_repeat('é', 500));
        $this->api('POST', "/me/invitations/{$other}/decline", token: $b['token'])->assertOk()->assertJsonPath('data.decline_reason', null);
        // Both notifications are made in the same second and their ids are random, so the order is not fixed.
        $reasons = array_column($this->notes($this->adminId, 'invitation.declined'), 'reason');
        sort($reasons);
        $this->assertSame([null, str_repeat('é', 500)], $reasons);
    }

    // ----- applying and expiry -----

    public function test_applying_to_the_job_marks_the_invitation_applied_even_after_a_decline(): void
    {
        $id = $this->invitationId($this->ana);
        $b = $this->candidate('budi.s@example.test', 'Budi Santoso');
        $bId = $this->invitationId($b);
        $this->api('POST', "/me/invitations/{$bId}/decline", token: $b['token'])->assertOk();
        $unrelated = $this->candidate('citra@example.test', 'Citra Dewi');

        $application = (int) $this->apply($this->job, ['resume_id' => $this->ana['resume']], $this->ana['token'])->assertCreated()->json('data.id');
        $bApplication = (int) $this->apply($this->job, ['resume_id' => $b['resume']], $b['token'])->assertCreated()->json('data.id');
        $this->apply($this->job, ['resume_id' => $unrelated['resume']], $unrelated['token'])->assertCreated();

        $row = DB::table('jobinvitation_invitations')->where('id', $id)->first();
        $this->assertSame('applied', $row->status);
        $this->assertSame($application, (int) $row->application_id);
        $this->assertNotNull($row->responded_at);
        $this->assertSame(['applied', $bApplication], [DB::table('jobinvitation_invitations')->where('id', $bId)->value('status'), (int) DB::table('jobinvitation_invitations')->where('id', $bId)->value('application_id')]);
        $this->assertSame(2, DB::table('jobinvitation_invitations')->count());

        $this->api('GET', '/me/invitations?filter[status]=applied', token: $this->ana['token'])->assertOk()->assertJsonPath('data.0.application_id', $application);
        $this->api('POST', "/me/invitations/{$id}/decline", token: $this->ana['token'])->assertStatus(409);
    }

    public function test_an_open_invitation_reads_as_expired_while_its_job_is_not_live(): void
    {
        $id = $this->invitationId($this->ana);

        DB::table('job_jobs')->where('id', $this->job)->update(['status' => 'closed']);
        $this->api('GET', '/me/invitations', token: $this->ana['token'])->assertOk()->assertJsonPath('data.0.status', 'sent')->assertJsonPath('data.0.expired', true);
        $this->api('GET', "/company/jobs/{$this->job}/invitations", token: $this->adminToken)->assertOk()->assertJsonPath('data.0.expired', true);
        $this->api('POST', "/me/invitations/{$id}/decline", token: $this->ana['token'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:invitation-closed');

        // Opening it still works and the invitation lives again with the job.
        $this->api('GET', "/me/invitations/{$id}", token: $this->ana['token'])->assertOk()->assertJsonPath('data.status', 'viewed')->assertJsonPath('data.expired', true);
        DB::table('job_jobs')->where('id', $this->job)->update(['status' => 'active']);
        $this->api('GET', "/me/invitations/{$id}", token: $this->ana['token'])->assertOk()->assertJsonPath('data.expired', false);

        // A deadline in the past expires it too; an answered invitation never reads as expired.
        DB::table('job_jobs')->where('id', $this->job)->update(['deadline' => '2026-10-04']);
        $this->api('GET', "/me/invitations/{$id}", token: $this->ana['token'])->assertOk()->assertJsonPath('data.expired', true);
        DB::table('job_jobs')->where('id', $this->job)->update(['deadline' => '2026-12-31']);
        $this->api('POST', "/me/invitations/{$id}/decline", token: $this->ana['token'])->assertOk()->assertJsonPath('data.expired', false);
        DB::table('job_jobs')->where('id', $this->job)->update(['status' => 'closed']);
        $this->api('GET', "/me/invitations/{$id}", token: $this->ana['token'])->assertOk()->assertJsonPath('data.status', 'declined')->assertJsonPath('data.expired', false);
    }

    // ----- the company's list -----

    public function test_the_company_lists_the_invitations_of_a_job_newest_first_with_the_filter(): void
    {
        $b = $this->candidate('budi.s@example.test', 'Budi Santoso');
        $first = $this->invitationId($this->ana, token: $this->recruiterToken);
        Carbon::setTestNow('2026-10-05 10:00:00');
        $second = $this->invitationId($b);
        $this->api('GET', "/me/invitations/{$first}", token: $this->ana['token'])->assertOk();
        $this->invitationId($b, $this->insertJob('Data Analyst', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']));

        $list = $this->api('GET', "/company/jobs/{$this->job}/invitations", token: $this->recruiterToken)->assertOk();
        $this->assertSame([$second, $first], $list->json('data.*.id'));
        $list->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.1.candidate.name', 'Ana Putri')
            ->assertJsonPath('data.1.invited_by.name', 'Rina Rekruter')
            ->assertJsonPath('data.1.status', 'viewed')
            ->assertJsonPath('data.1.viewed_at', '2026-10-05T10:00:00+00:00')
            ->assertJsonPath('data.0.invited_by.name', 'Andi Admin');

        $this->assertSame([$first], $this->api('GET', "/company/jobs/{$this->job}/invitations?filter[status]=viewed", token: $this->adminToken)->assertOk()->json('data.*.id'));
        $this->api('GET', "/company/jobs/{$this->job}/invitations?filter[status]=nope", token: $this->adminToken)->assertStatus(422);
        $this->api('GET', "/company/jobs/{$this->job}/invitations?per_page=1", token: $this->adminToken)->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
    }

    // ----- notifications -----

    public function test_the_email_follows_the_preference_and_the_verified_address_but_the_notification_is_always_written(): void
    {
        Mail::fake();
        $b = $this->candidate('budi.s@example.test', 'Budi Santoso');
        $c = $this->candidate('citra@example.test', 'Citra Dewi');

        $this->api('PUT', '/me/notification-preferences/invitation/email', ['enabled' => false], $b['token'])->assertNoContent();
        $this->invitationId($b);
        DB::table('identity_users')->where('id', $c['id'])->update(['email_verified_at' => null]);
        $this->invitationId($c);
        Mail::assertNothingQueued();
        $this->assertCount(1, $this->notes($b['id'], 'invitation.received'));
        $this->assertCount(1, $this->notes($c['id'], 'invitation.received'));

        $this->invitationId($this->ana);
        Mail::assertQueued(InvitationMail::class, 1);
    }

    public function test_a_failed_invitation_tells_nobody(): void
    {
        Mail::fake();

        $this->invite($this->ana, body: ['note' => str_repeat('a', 1001)])->assertStatus(422);
        $this->invite($this->ana, $this->insertJob('Draft job', 'draft'))->assertStatus(422);

        $this->assertSame([], $this->notes($this->ana['id'], 'invitation.received'));
        Mail::assertNothingQueued();
    }

    // ----- erasure -----

    public function test_anonymising_the_candidate_deletes_their_invitations_only(): void
    {
        $b = $this->candidate('budi.s@example.test', 'Budi Santoso');
        $this->invitationId($this->ana);
        $this->invitationId($b);

        Event::dispatch(new UserAnonymized($this->ana['id'], now()->toDateTimeImmutable()));

        $this->assertSame([$b['id']], DB::table('jobinvitation_invitations')->pluck('user_id')->map(fn ($id): int => (int) $id)->all());
    }
}
