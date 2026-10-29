<?php

namespace Tests\Feature\TalentPool;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Recruitment\Application\EraseApplicantData;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-talent-pool.md` sections 1 and 3: consent when applying, saving applicants, their basis and retention, the richer list and the entry page. */
final class TalentPoolApplicantsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    /** @var array{id: int, slug: string, token: string, resume: int} */
    private array $ana;

    /** @var array{id: int, slug: string, token: string, resume: int} */
    private array $budi;

    private int $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->ana = $this->candidate('ana@example.test', 'Ana Putri');
        $this->budi = $this->candidate('budi.c@example.test', 'Budi Candidate');
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /**
     * A job seeker with a complete profile who can be found.
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

    private function hide(array $candidate): void
    {
        DB::table('jobseeker_profiles')->where('user_id', $candidate['id'])->update(['is_discoverable' => false]);
    }

    /** The candidate applies to a job, with or without the consent; returns the id of the application. */
    private function applyAs(array $candidate, bool $consent, ?int $job = null): int
    {
        return (int) $this->apply($job ?? $this->job, ['resume_id' => $candidate['resume'], ...($consent ? ['talent_pool_consent' => true] : [])], $candidate['token'])->assertCreated()->json('data.id');
    }

    /** @param  array<string, mixed>  $extra */
    private function saveApplication(int $applicationId, ?string $token = null, array $extra = []): TestResponse
    {
        return $this->api('POST', '/company/talent-pool', ['application_id' => $applicationId, ...$extra], $token ?? $this->recruiterToken);
    }

    private function entryOf(int $applicationId): int
    {
        return (int) $this->saveApplication($applicationId)->assertCreated()->json('data.id');
    }

    private function listing(string $query = '', ?string $token = null): TestResponse
    {
        return $this->api('GET', '/company/talent-pool'.$query, token: $token ?? $this->recruiterToken)->assertOk();
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function ageApplication(int $applicationId, int $months): void
    {
        DB::table('recruitment_applications')->where('id', $applicationId)->update(['applied_at' => now()->subMonths($months)]);
    }

    public function test_the_consent_is_off_unless_the_applicant_ticks_it_and_both_sides_see_it(): void
    {
        $without = $this->applyAs($this->budi, false);
        $with = $this->applyAs($this->ana, true);

        $this->assertNull(DB::table('recruitment_applications')->where('id', $without)->value('talent_pool_consent_at'));
        $this->assertNotNull(DB::table('recruitment_applications')->where('id', $with)->value('talent_pool_consent_at'));

        $this->api('GET', "/me/applications/{$without}", token: $this->budi['token'])->assertOk()->assertJsonPath('data.talent_pool_consent', false);
        $this->api('GET', "/me/applications/{$with}", token: $this->ana['token'])->assertOk()->assertJsonPath('data.talent_pool_consent', true);
        $this->api('GET', "/company/applications/{$with}", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.talent_pool_consent', true);
        $this->api('GET', "/company/applications/{$without}", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.talent_pool_consent', false);
    }

    public function test_an_applicant_gives_the_consent_later_and_takes_it_back(): void
    {
        $id = $this->applyAs($this->budi, false);

        $this->api('PUT', "/me/applications/{$id}/talent-pool-consent", token: $this->budi['token'])->assertNoContent();
        $first = DB::table('recruitment_applications')->where('id', $id)->value('talent_pool_consent_at');
        $this->assertNotNull($first);

        // Giving it again keeps the first moment.
        Carbon::setTestNow(now()->addHour());
        $this->api('PUT', "/me/applications/{$id}/talent-pool-consent", token: $this->budi['token'])->assertNoContent();
        $this->assertSame($first, DB::table('recruitment_applications')->where('id', $id)->value('talent_pool_consent_at'));

        $this->api('DELETE', "/me/applications/{$id}/talent-pool-consent", token: $this->budi['token'])->assertNoContent();
        $this->api('DELETE', "/me/applications/{$id}/talent-pool-consent", token: $this->budi['token'])->assertNoContent();
        $this->assertNull(DB::table('recruitment_applications')->where('id', $id)->value('talent_pool_consent_at'));
    }

    public function test_the_consent_belongs_to_the_applicant_alone(): void
    {
        $id = $this->applyAs($this->budi, false);

        $this->api('PUT', "/me/applications/{$id}/talent-pool-consent", token: $this->ana['token'])->assertNotFound();
        $this->api('DELETE', "/me/applications/{$id}/talent-pool-consent", token: $this->ana['token'])->assertNotFound();
        $this->api('PUT', "/me/applications/{$id}/talent-pool-consent", token: $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        $this->api('PUT', '/me/applications/999999/talent-pool-consent', token: $this->budi['token'])->assertNotFound();
    }

    public function test_a_member_saves_the_applicant_of_a_consenting_application_and_sees_the_snapshot_not_the_live_profile(): void
    {
        $this->api('PUT', '/me/skills', ['skill_ids' => [$this->skillId('php')]], $this->ana['token'])->assertOk();
        $applicationId = $this->applyAs($this->ana, true);
        $this->hide($this->ana);

        // The live profile changes after applying; the pool keeps showing what the applicant sent.
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['headline' => 'Changed after applying']);

        $response = $this->saveApplication($applicationId, extra: ['note' => 'Call back in spring']);

        $response->assertCreated()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.source', 'application')
            ->assertJsonPath('data.candidate', ['slug' => null, 'name' => 'Ana Putri', 'has_avatar' => false])
            ->assertJsonPath('data.headline', 'Backend developer')
            ->assertJsonPath('data.city', 'BANDUNG')
            ->assertJsonPath('data.latest_position', ['title' => 'Backend Developer', 'employer' => 'PT Lama'])
            ->assertJsonPath('data.skills', ['php'])
            ->assertJsonPath('data.note', 'Call back in spring')
            ->assertJsonPath('data.saved_by', ['id' => $this->recruiterId, 'name' => 'Rina Rekruter'])
            ->assertJsonPath('data.last_application.id', $applicationId)
            ->assertJsonPath('data.last_application.job_title', 'Backend Developer')
            ->assertJsonPath('data.last_application.status', 'applied')
            ->assertJsonPath('data.last_invitation', null);

        $this->assertStringNotContainsString('Changed after applying', (string) $response->getContent());
        $this->assertStringNotContainsString('ana@example.test', (string) $response->getContent());
        $this->assertStringNotContainsString('081234567890', (string) $response->getContent());

        $row = DB::table('talentpool_entries')->first();
        $this->assertSame('application', $row->source);
        $this->assertSame($applicationId, (int) $row->application_id);
        $this->assertSame($this->recruiterId, (int) $row->saved_by_user_id);
    }

    public function test_an_applicant_without_consent_is_saved_only_when_they_can_be_found_anyway(): void
    {
        $budiApplication = $this->applyAs($this->budi, false);
        $this->hide($this->budi);

        $this->saveApplication($budiApplication)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:talent-pool-consent-missing');
        $this->assertSame(0, DB::table('talentpool_entries')->count());

        // A findable person needs no consent; the entry rests on being findable, so it keeps no application.
        $anaApplication = $this->applyAs($this->ana, false);
        $this->saveApplication($anaApplication)->assertCreated()->assertJsonPath('data.source', 'application')->assertJsonPath('data.candidate.slug', $this->ana['slug']);
        $this->assertNull(DB::table('talentpool_entries')->value('application_id'));
    }

    public function test_a_hired_applicant_is_not_saved(): void
    {
        $applicationId = $this->applyAs($this->ana, true);
        DB::table('recruitment_applications')->where('id', $applicationId)->update(['stage_id' => $this->stage('hired'), 'stage_type' => 'hired']);

        $this->saveApplication($applicationId)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-hired');
        $this->assertSame(0, DB::table('talentpool_entries')->count());
    }

    public function test_every_other_stage_may_be_saved_from(): void
    {
        foreach (['screening', 'interview', 'offer', 'rejected', 'withdrawn'] as $index => $type) {
            $candidate = $this->candidate("stage{$index}@example.test", "Stage {$index}");
            $applicationId = $this->applyAs($candidate, true);
            DB::table('recruitment_applications')->where('id', $applicationId)->update(['stage_id' => $this->stage($type), 'stage_type' => $type]);

            $this->saveApplication($applicationId)->assertCreated()->assertJsonPath('data.last_application.status', $type);
        }
    }

    public function test_an_applicant_is_saved_once_and_only_by_a_member_who_may_see_the_application(): void
    {
        $applicationId = $this->applyAs($this->ana, true);

        // The second recruiter is not assigned to the job, so the application does not exist for them.
        $this->saveApplication($applicationId, $this->secondRecruiterToken)->assertNotFound();
        $this->saveApplication($applicationId, $this->otherAdminToken)->assertNotFound();
        $this->saveApplication(999_999)->assertNotFound();

        $this->entryOf($applicationId);
        $this->saveApplication($applicationId, $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-in-talent-pool');
        $this->assertSame(1, DB::table('talentpool_entries')->count());
    }

    public function test_the_member_needs_the_talent_pool_and_the_applications_permission(): void
    {
        $applicationId = $this->applyAs($this->ana, true);
        $noPool = $this->memberWith(['applications.view', 'jobs.view_all'])['token'];
        $noApplications = $this->memberWith(['talent_pool.manage', 'jobs.view_all'], 'pool-only@example.test')['token'];

        $this->saveApplication($applicationId, $noPool)->assertForbidden()->assertJsonPath('permission', 'talent_pool.manage');
        $this->saveApplication($applicationId, $noApplications)->assertForbidden()->assertJsonPath('permission', 'applications.view');
    }

    public function test_one_of_candidate_and_application_id_is_required_never_both(): void
    {
        $applicationId = $this->applyAs($this->ana, true);

        $this->api('POST', '/company/talent-pool', [], $this->recruiterToken)->assertStatus(422);
        $this->api('POST', '/company/talent-pool', ['candidate' => $this->ana['slug'], 'application_id' => $applicationId], $this->recruiterToken)->assertStatus(422);
        $this->assertSame(0, DB::table('talentpool_entries')->count());
    }

    public function test_a_consent_older_than_twenty_four_months_no_longer_counts(): void
    {
        $applicationId = $this->applyAs($this->ana, true);
        $this->hide($this->ana);
        $this->ageApplication($applicationId, 25);

        $this->saveApplication($applicationId)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:talent-pool-consent-missing');

        $this->ageApplication($applicationId, 23);
        $this->saveApplication($applicationId)->assertCreated();
    }

    public function test_an_entry_resting_on_a_consent_shows_nothing_once_the_consent_lapses_and_the_note_goes_at_night(): void
    {
        $applicationId = $this->applyAs($this->ana, true);
        $this->hide($this->ana);
        $this->saveApplication($applicationId, extra: ['note' => 'Private words about Ana'])->assertCreated();

        // Inside the window the note stays through the nightly clean-up.
        Artisan::call('talentpool:clear-hidden-notes');
        $this->assertStringContainsString('Cleared 0 notes.', Artisan::output());
        $this->assertSame('Private words about Ana', $this->listing()->json('data.0.note'));

        $this->ageApplication($applicationId, 25);

        $row = $this->listing()->json('data.0');
        $this->assertFalse($row['available']);
        $this->assertNull($row['candidate']);
        $this->assertNull($row['note']);
        $this->assertNull($row['last_application']);
        $this->assertStringNotContainsString('Ana', (string) json_encode($row));

        Artisan::call('talentpool:clear-hidden-notes');
        $this->assertStringContainsString('Cleared 1 note.', Artisan::output());
        $this->assertNull(DB::table('talentpool_entries')->value('note'));
    }

    public function test_the_nightly_purge_deletes_entries_that_rested_on_a_lapsed_consent_only(): void
    {
        $anaApplication = $this->applyAs($this->ana, true);
        $budiApplication = $this->applyAs($this->budi, true);
        $citra = $this->candidate('citra@example.test', 'Citra Other');
        $citraApplication = $this->applyAs($citra, false);
        $this->hide($this->ana);

        $ana = $this->entryOf($anaApplication);
        $budi = $this->entryOf($budiApplication);
        // Found in the search: it rests on being findable, whatever its source.
        $this->saveApplication($citraApplication)->assertCreated();
        $searched = (int) $this->api('POST', '/company/talent-pool', ['candidate' => $this->budi['slug']], $this->otherAdminToken)->assertCreated()->json('data.id');

        // Fresh consents: nothing to delete.
        Artisan::call('talentpool:purge-lapsed-consent');
        $this->assertStringContainsString('Deleted 0 entries.', Artisan::output());

        foreach ([$anaApplication, $budiApplication, $citraApplication] as $applicationId) {
            $this->ageApplication($applicationId, 25);
        }

        Artisan::call('talentpool:purge-lapsed-consent');

        $this->assertStringContainsString('Deleted 1 entry.', Artisan::output());
        $this->assertFalse(DB::table('talentpool_entries')->where('id', $ana)->exists());
        $this->assertTrue(DB::table('talentpool_entries')->where('id', $budi)->exists());
        $this->assertTrue(DB::table('talentpool_entries')->where('id', $searched)->exists());
        $this->assertSame(3, DB::table('talentpool_entries')->count());

        Artisan::call('talentpool:purge-lapsed-consent');
        $this->assertStringContainsString('Deleted 0 entries.', Artisan::output());
    }

    public function test_the_purge_is_scheduled_nightly_without_overlapping(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'talentpool:purge-lapsed-consent'));

        $this->assertCount(1, $events);
        $this->assertSame('10 4 * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    public function test_revoking_the_consent_removes_the_entry_that_rested_on_it_with_its_note(): void
    {
        $applicationId = $this->applyAs($this->ana, true);
        $this->hide($this->ana);
        $this->saveApplication($applicationId, extra: ['note' => 'Private words about Ana'])->assertCreated();

        $this->api('DELETE', "/me/applications/{$applicationId}/talent-pool-consent", token: $this->ana['token'])->assertNoContent();

        $this->assertSame(0, DB::table('talentpool_entries')->count());
    }

    public function test_revoking_keeps_the_entry_while_another_consenting_application_or_the_search_still_holds_the_person(): void
    {
        // Another consenting application of the same company.
        $secondJob = $this->insertJob('Frontend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $first = $this->applyAs($this->ana, true);
        $this->applyAs($this->ana, true, $secondJob);
        $this->hide($this->ana);
        $this->entryOf($first);

        $this->api('DELETE', "/me/applications/{$first}/talent-pool-consent", token: $this->ana['token'])->assertNoContent();
        $this->assertSame(1, DB::table('talentpool_entries')->count());

        // Findable: the entry stays on that basis.
        $budiApplication = $this->applyAs($this->budi, true);
        $this->entryOf($budiApplication);

        $this->api('DELETE', "/me/applications/{$budiApplication}/talent-pool-consent", token: $this->budi['token'])->assertNoContent();
        $this->assertSame(2, DB::table('talentpool_entries')->count());
    }

    public function test_revoking_keeps_an_entry_found_in_the_search(): void
    {
        $applicationId = $this->applyAs($this->ana, true);
        $this->hide($this->ana);
        $this->saveApplication($applicationId)->assertCreated();
        DB::table('talentpool_entries')->update(['source' => 'search', 'application_id' => null]);

        $this->api('DELETE', "/me/applications/{$applicationId}/talent-pool-consent", token: $this->ana['token'])->assertNoContent();

        $this->assertSame(1, DB::table('talentpool_entries')->count());
    }

    public function test_the_consent_goes_with_an_anonymised_account(): void
    {
        $applicationId = $this->applyAs($this->ana, true);

        $this->app->make(EraseApplicantData::class)->execute($this->ana['id']);

        $this->assertNull(DB::table('recruitment_applications')->where('id', $applicationId)->value('talent_pool_consent_at'));
    }

    public function test_the_list_shows_the_summary_of_a_findable_candidate(): void
    {
        $this->api('PUT', '/me/skills', ['skill_ids' => [$this->skillId('php'), $this->skillId('laravel')]], $this->budi['token'])->assertOk();
        $this->api('POST', '/company/talent-pool', ['candidate' => $this->budi['slug']], $this->recruiterToken)->assertCreated();

        $row = $this->listing()->json('data.0');

        $this->assertSame(['slug' => $this->budi['slug'], 'name' => 'Budi Candidate', 'has_avatar' => false], $row['candidate']);
        $this->assertSame('search', $row['source']);
        $this->assertSame('Backend developer', $row['headline']);
        $this->assertSame('BANDUNG', $row['city']);
        $this->assertSame(['title' => 'Backend Developer', 'employer' => 'PT Lama'], $row['latest_position']);
        $this->assertEqualsCanonicalizing(['php', 'laravel'], $row['skills']);
        $this->assertSame(['id' => $this->recruiterId, 'name' => 'Rina Rekruter'], $row['saved_by']);
        $this->assertNull($row['last_application']);
        $this->assertNull($row['last_invitation']);
    }

    public function test_an_entry_saved_before_the_columns_existed_has_no_saver(): void
    {
        $this->api('POST', '/company/talent-pool', ['candidate' => $this->budi['slug']], $this->recruiterToken)->assertCreated();
        DB::table('talentpool_entries')->update(['saved_by_user_id' => null]);

        $this->assertNull($this->listing()->json('data.0.saved_by'));
    }

    public function test_the_last_application_shows_its_id_only_to_a_member_who_may_see_it(): void
    {
        $applicationId = $this->applyAs($this->ana, true);
        $this->entryOf($applicationId);

        $this->assertSame($applicationId, $this->listing()->json('data.0.last_application.id'));

        // The admin sees every job; a recruiter who is not assigned to the job still sees the pool but only the title and status of the application.
        $row = $this->listing(token: $this->secondRecruiterToken)->json('data.0.last_application');
        $this->assertNull($row['id']);
        $this->assertSame('Backend Developer', $row['job_title']);
        $this->assertSame('applied', $row['status']);
    }

    public function test_the_list_filters_by_text_and_source_and_pages_after_filtering(): void
    {
        $this->api('PUT', '/me/skills', ['skill_ids' => [$this->skillId('php')]], $this->ana['token'])->assertOk();
        $anaApplication = $this->applyAs($this->ana, true);
        $ana = $this->entryOf($anaApplication);
        $budi = (int) $this->api('POST', '/company/talent-pool', ['candidate' => $this->budi['slug']], $this->recruiterToken)->assertCreated()->json('data.id');

        $ids = fn (string $query): array => $this->listing($query)->json('data.*.id');

        $this->assertEqualsCanonicalizing([$ana, $budi], $ids(''));
        $this->assertSame([$ana], $ids('?'.http_build_query(['filter' => ['q' => 'ANA']])));
        $this->assertSame([$ana], $ids('?'.http_build_query(['filter' => ['q' => 'php']])));
        $this->assertSame([$budi], $ids('?'.http_build_query(['filter' => ['q' => 'candidate']])));
        $this->assertEqualsCanonicalizing([$ana, $budi], $ids('?'.http_build_query(['filter' => ['q' => 'pt lama']])));
        $this->assertSame([], $ids('?'.http_build_query(['filter' => ['q' => 'nobody']])));
        $this->assertSame([$ana], $ids('?'.http_build_query(['filter' => ['source' => 'application']])));
        $this->assertSame([$budi], $ids('?'.http_build_query(['filter' => ['source' => 'search']])));
        $this->assertSame(2, $this->listing('?'.http_build_query(['filter' => ['q' => 'pt lama'], 'per_page' => 1]))->json('meta.total'));
        $this->api('GET', '/company/talent-pool?'.http_build_query(['filter' => ['source' => 'other']]), token: $this->recruiterToken)->assertStatus(422);
    }

    public function test_an_entry_that_is_not_available_never_matches_a_search(): void
    {
        $this->api('POST', '/company/talent-pool', ['candidate' => $this->budi['slug']], $this->recruiterToken)->assertCreated();
        $this->hide($this->budi);

        $this->assertSame([], $this->listing('?'.http_build_query(['filter' => ['q' => 'budi']]))->json('data'));
        $this->assertCount(1, $this->listing()->json('data'));
    }

    public function test_the_list_sorts_by_the_latest_activity_when_asked(): void
    {
        Carbon::setTestNow('2026-10-01 09:00:00');
        $budi = (int) $this->api('POST', '/company/talent-pool', ['candidate' => $this->budi['slug']], $this->recruiterToken)->assertCreated()->json('data.id');

        Carbon::setTestNow('2026-10-03 09:00:00');
        $ana = $this->entryOf($this->applyAs($this->ana, true));

        // Budi is invited the day after Ana applied, so his latest activity is the newer one although he was saved first.
        Carbon::setTestNow('2026-10-04 09:00:00');
        $this->api('POST', "/company/jobs/{$this->job}/invitations", ['candidate' => $this->budi['slug']], $this->adminToken)->assertCreated();

        Carbon::setTestNow('2026-10-05 09:00:00');

        $this->assertSame([$ana, $budi], $this->listing()->json('data.*.id'));
        $this->assertSame([$budi, $ana], $this->listing('?sort=last_activity')->json('data.*.id'));

        $invitation = $this->listing('?sort=last_activity')->json('data.0.last_invitation');
        $this->assertSame('Backend Developer', $invitation['job_title']);
        $this->assertSame('sent', $invitation['status']);

        $this->api('GET', '/company/talent-pool?sort=random', token: $this->recruiterToken)->assertStatus(422);
    }

    public function test_the_entry_page_of_a_person_kept_through_consent_shows_the_snapshot_without_contact_data(): void
    {
        $applicationId = $this->applyAs($this->ana, true);
        $this->hide($this->ana);
        $id = $this->entryOf($applicationId);

        $response = $this->api('GET', "/company/talent-pool/{$id}", token: $this->recruiterToken)->assertOk();

        $response->assertJsonPath('data.candidate', ['slug' => null, 'name' => 'Ana Putri', 'has_avatar' => false])
            ->assertJsonPath('data.profile.headline', 'Backend developer')
            ->assertJsonPath('data.profile.experiences.0.job_title', 'Backend Developer')
            ->assertJsonPath('data.applications.0.id', $applicationId)
            ->assertJsonPath('data.applications.0.job_title', 'Backend Developer');

        $profile = $response->json('data.profile');
        $this->assertArrayNotHasKey('email', $profile);
        $this->assertArrayNotHasKey('phone', $profile);
        $this->assertArrayNotHasKey('whatsapp', $profile);
        $this->assertArrayNotHasKey('links', $profile);
        $this->assertArrayNotHasKey('address_line', $profile['location']);
        $this->assertStringNotContainsString('081234567890', (string) $response->getContent());
    }

    public function test_the_entry_page_of_a_findable_person_points_to_their_profile_and_hides_what_is_not_the_members_to_see(): void
    {
        $applicationId = $this->applyAs($this->ana, true);
        $id = $this->entryOf($applicationId);

        $this->api('GET', "/company/talent-pool/{$id}", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.candidate.slug', $this->ana['slug'])
            ->assertJsonPath('data.profile', null)
            ->assertJsonPath('data.applications.0.id', $applicationId);

        // A member who does not see the job gets no application links.
        $this->api('GET', "/company/talent-pool/{$id}", token: $this->secondRecruiterToken)->assertOk()->assertJsonPath('data.applications', []);
    }

    public function test_the_entry_page_of_an_unavailable_entry_and_of_another_company_shows_nothing(): void
    {
        $applicationId = $this->applyAs($this->ana, true);
        $this->hide($this->ana);
        $id = $this->entryOf($applicationId);
        $this->ageApplication($applicationId, 25);

        $this->api('GET', "/company/talent-pool/{$id}", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.profile', null)
            ->assertJsonPath('data.applications', []);

        $this->api('GET', "/company/talent-pool/{$id}", token: $this->otherAdminToken)->assertNotFound();
        $this->api('GET', '/company/talent-pool/999999', token: $this->recruiterToken)->assertNotFound();
    }
}
