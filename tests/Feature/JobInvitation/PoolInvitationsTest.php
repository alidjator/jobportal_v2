<?php

namespace Tests\Feature\JobInvitation;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-talent-pool.md` section 2: inviting people from the talent pool, one at a time and in bulk. */
final class PoolInvitationsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    /** @var array{id: int, slug: string, token: string, resume: int} */
    private array $ana;

    /** @var array{id: int, slug: string, token: string, resume: int} */
    private array $budi;

    private int $job;

    private int $anaEntry;

    private int $budiEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->ana = $this->candidate('ana@example.test', 'Ana Putri');
        $this->budi = $this->candidate('budi.c@example.test', 'Budi Candidate');
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->anaEntry = $this->poolEntry($this->ana);
        $this->budiEntry = $this->poolEntry($this->budi);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @return array{id: int, slug: string, token: string, resume: int} */
    private function candidate(string $email, string $name): array
    {
        $id = $this->seeker($email, name: $name);
        $token = $this->tokenFor($id);
        $resume = $this->completeProfile($token);
        DB::table('jobseeker_profiles')->where('user_id', $id)->update(['is_discoverable' => true]);

        return ['id' => $id, 'slug' => (string) DB::table('jobseeker_profiles')->where('user_id', $id)->value('slug'), 'token' => $token, 'resume' => $resume];
    }

    /** @param  array{slug: string}  $candidate */
    private function poolEntry(array $candidate, ?string $token = null): int
    {
        return (int) $this->api('POST', '/company/talent-pool', ['candidate' => $candidate['slug']], $token ?? $this->adminToken)->assertCreated()->json('data.id');
    }

    private function hide(array $candidate): void
    {
        DB::table('jobseeker_profiles')->where('user_id', $candidate['id'])->update(['is_discoverable' => false]);
    }

    /**
     * @param  list<int>  $entryIds
     * @param  array<string, mixed>  $extra
     */
    private function bulk(array $entryIds, ?int $job = null, ?string $token = null, array $extra = []): TestResponse
    {
        return $this->api('POST', '/company/jobs/'.($job ?? $this->job).'/invitations/bulk', ['entry_ids' => $entryIds, ...$extra], $token ?? $this->adminToken);
    }

    public function test_one_entry_is_invited_by_its_id_and_the_candidate_is_told_as_usual(): void
    {
        $response = $this->api('POST', "/company/jobs/{$this->job}/invitations", ['talent_pool_entry_id' => $this->anaEntry, 'note' => 'Please apply'], $this->adminToken);

        $response->assertCreated()->assertJsonPath('data.candidate.slug', $this->ana['slug'])->assertJsonPath('data.note', 'Please apply');
        $this->assertSame(1, DB::table('jobinvitation_invitations')->where('user_id', $this->ana['id'])->count());

        // The candidate sees the invitation without any trace of the pool.
        $mine = $this->api('GET', '/me/invitations', token: $this->ana['token'])->assertOk();
        $this->assertStringNotContainsString('pool', strtolower((string) $mine->getContent()));
    }

    public function test_an_entry_id_follows_the_rules_of_an_invitation(): void
    {
        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['talent_pool_entry_id' => $this->anaEntry], $this->adminToken)->assertCreated();

        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['talent_pool_entry_id' => $this->anaEntry], $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-invited');

        $this->apply($this->job, ['resume_id' => $this->budi['resume']], $this->budi['token'])->assertCreated();
        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['talent_pool_entry_id' => $this->budiEntry], $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:already-applied');
    }

    public function test_an_entry_of_another_company_or_a_missing_one_does_not_exist_and_an_unavailable_one_is_refused(): void
    {
        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['talent_pool_entry_id' => 999_999], $this->adminToken)->assertNotFound();

        $foreign = $this->poolEntry($this->ana, $this->otherAdminToken);
        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['talent_pool_entry_id' => $foreign], $this->adminToken)->assertNotFound();

        $this->hide($this->ana);
        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['talent_pool_entry_id' => $this->anaEntry], $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:entry-unavailable');
        $this->assertSame(0, DB::table('jobinvitation_invitations')->count());
    }

    public function test_a_person_kept_through_consent_alone_can_be_invited(): void
    {
        $applicationJob = $this->insertJob('Frontend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $applicationId = (int) $this->apply($applicationJob, ['resume_id' => $this->ana['resume'], 'talent_pool_consent' => true], $this->ana['token'])->assertCreated()->json('data.id');
        $this->api('DELETE', "/company/talent-pool/{$this->anaEntry}", token: $this->adminToken)->assertNoContent();
        $this->hide($this->ana);
        $entry = (int) $this->api('POST', '/company/talent-pool', ['application_id' => $applicationId], $this->adminToken)->assertCreated()->json('data.id');

        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['talent_pool_entry_id' => $entry], $this->adminToken)->assertCreated();

        // Once the consent is taken back the entry is gone and there is nobody to invite.
        $this->api('DELETE', "/me/applications/{$applicationId}/talent-pool-consent", token: $this->ana['token'])->assertNoContent();
        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['talent_pool_entry_id' => $entry], $this->adminToken)->assertNotFound();
    }

    public function test_one_of_candidate_and_entry_id_is_required_never_both(): void
    {
        $this->api('POST', "/company/jobs/{$this->job}/invitations", [], $this->adminToken)->assertStatus(422);
        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['candidate' => $this->ana['slug'], 'talent_pool_entry_id' => $this->anaEntry], $this->adminToken)->assertStatus(422);
    }

    public function test_a_bulk_invitation_sends_one_invitation_per_entry_and_says_what_happened_to_each(): void
    {
        $response = $this->bulk([$this->anaEntry, $this->budiEntry], extra: ['note' => 'We have a new opening']);

        $response->assertOk()->assertJsonPath('data', [
            ['entry_id' => $this->anaEntry, 'status' => 'invited', 'code' => null, 'invitation_id' => (int) DB::table('jobinvitation_invitations')->where('user_id', $this->ana['id'])->value('id')],
            ['entry_id' => $this->budiEntry, 'status' => 'invited', 'code' => null, 'invitation_id' => (int) DB::table('jobinvitation_invitations')->where('user_id', $this->budi['id'])->value('id')],
        ]);
        $this->assertSame(2, DB::table('jobinvitation_invitations')->count());
        $this->assertSame(['We have a new opening', 'We have a new opening'], DB::table('jobinvitation_invitations')->orderBy('id')->pluck('note')->all());
    }

    public function test_an_entry_that_cannot_be_invited_is_skipped_and_the_others_still_go_out(): void
    {
        $citra = $this->candidate('citra@example.test', 'Citra Other');
        $citraEntry = $this->poolEntry($citra);
        $dewi = $this->candidate('dewi@example.test', 'Dewi Applied');
        $dewiEntry = $this->poolEntry($dewi);
        $foreign = $this->poolEntry($citra, $this->otherAdminToken);

        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['talent_pool_entry_id' => $this->anaEntry], $this->adminToken)->assertCreated();
        $this->apply($this->job, ['resume_id' => $dewi['resume']], $dewi['token'])->assertCreated();
        $this->hide($this->budi);

        $rows = $this->bulk([$this->anaEntry, $this->budiEntry, $dewiEntry, $foreign, 999_999, $citraEntry])->assertOk()->json('data');

        $this->assertSame(
            [
                [$this->anaEntry, 'skipped', 'already-invited'],
                [$this->budiEntry, 'skipped', 'entry-unavailable'],
                [$dewiEntry, 'skipped', 'already-applied'],
                [$foreign, 'skipped', 'entry-unavailable'],
                [999_999, 'skipped', 'entry-unavailable'],
                [$citraEntry, 'invited', null],
            ],
            array_map(static fn (array $row): array => [$row['entry_id'], $row['status'], $row['code']], $rows),
        );
        $this->assertNotNull($rows[5]['invitation_id']);
        $this->assertNull($rows[0]['invitation_id']);
        $this->assertSame(2, DB::table('jobinvitation_invitations')->count());
    }

    public function test_the_daily_cap_applies_to_a_bulk_invitation_and_the_rest_is_skipped(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('invitations.max_per_day', 1, null);
        $citra = $this->candidate('citra@example.test', 'Citra Other');
        $citraEntry = $this->poolEntry($citra);

        $rows = $this->bulk([$this->anaEntry, $this->budiEntry, $citraEntry])->assertOk()->json('data');

        $this->assertSame(['invited', 'skipped', 'skipped'], array_column($rows, 'status'));
        $this->assertSame([null, 'invitation-limit', 'invitation-limit'], array_column($rows, 'code'));
        $this->assertSame(1, DB::table('jobinvitation_invitations')->count());
    }

    public function test_a_bulk_invitation_checks_the_request_and_the_job_before_sending_anything(): void
    {
        $this->bulk([])->assertStatus(422)->assertJsonValidationErrors(['entry_ids']);
        $this->bulk(range(1, 51))->assertStatus(422)->assertJsonValidationErrors(['entry_ids']);
        $this->bulk([$this->anaEntry, $this->anaEntry])->assertStatus(422)->assertJsonValidationErrors(['entry_ids.1']);
        $this->bulk([$this->anaEntry], extra: ['note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:note-too-long');

        $draft = $this->insertJob('Draft job', 'draft', [$this->recruiterId]);
        $this->bulk([$this->anaEntry], $draft)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:job-not-live');

        $this->bulk([$this->anaEntry], 999_999)->assertNotFound();

        // A recruiter who is not assigned to the job does not see it.
        $this->bulk([$this->anaEntry], token: $this->secondRecruiterToken)->assertNotFound();
        $this->assertSame(0, DB::table('jobinvitation_invitations')->count());
    }

    public function test_only_a_member_who_may_search_candidates_invites_from_the_pool(): void
    {
        $limited = $this->memberWith(['talent_pool.manage', 'jobs.view_all'])['token'];

        $this->bulk([$this->anaEntry], token: $limited)->assertForbidden()->assertJsonPath('permission', 'candidates.search');
        $this->bulk([$this->anaEntry], token: $this->ana['token'])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->assertSame(0, DB::table('jobinvitation_invitations')->count());
    }
}
