<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\LegacyImport\Application\ImportReport;
use Src\LegacyImport\Application\ImportRunner;
use Tests\Concerns\CreatesReferenceRows;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-activation.md` section 9: the saved jobs, the job-alert opt-ins and the saved candidates (a private list of the company) of the karir.pkp import. */
final class SavedItemsImportersTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    private int $ownerUser;

    private int $company;

    private int $sitiUser;

    private int $siti;

    private int $budiUser;

    private int $budi;

    private int $backend;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-08 09:00:00');
        $this->setUpLegacyDatabase();
        $this->locationChain();
        $this->industryId();
        $this->organizationTypeId();
        $this->referenceId('reference_job_roles', 'backend-developer');
        $this->referenceId('reference_job_roles', 'frontend-developer');
        $this->legacyMasterItem('industry_types', 1, ['en' => 'technology']);
        $this->legacyMasterItem('organization_types', 1, ['en' => 'private-company']);
        $this->legacyMasterItem('job_roles', 1, ['en' => 'backend-developer']);
        $this->legacyMasterItem('job_roles', 2, ['en' => 'frontend-developer']);

        $this->ownerUser = $this->legacyUser(['name' => 'PT Maju Jaya', 'role' => 'company']);
        $this->company = $this->legacyCompany($this->ownerUser, 1, 1);
        $this->sitiUser = $this->legacyUser(['name' => 'Siti Rahma', 'email' => 'siti@example.test'], ['title' => 'Backend Engineer', 'phone' => '08123456789']);
        $this->siti = $this->legacyCandidateId($this->sitiUser);
        $this->budiUser = $this->legacyUser(['name' => 'Budi Santoso', 'email' => 'budi@example.test'], ['title' => 'Designer', 'phone' => '08111111111']);
        $this->budi = $this->legacyCandidateId($this->budiUser);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function legacyJob(string $title = 'Backend Developer'): int
    {
        return $this->legacyRow('jobs', ['company_id' => $this->company, 'title' => $title, 'status' => 'pending', 'apply_on' => 'app', 'created_at' => '2026-08-01 08:00:00']);
    }

    private function newId(string $entity, int $legacyId): int
    {
        return (int) DB::table('legacyimport_id_map')->where('entity', $entity)->where('legacy_id', $legacyId)->value('new_id');
    }

    /** Runs what the saved items need, then the importers named (a dry run only for the last one). */
    private function import(string $entity, bool $write = true): ImportReport
    {
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'companies', 'profiles', 'jobs'] as $step) {
            $runner->run($step, true);
        }

        return $runner->run($entity, $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report, string $entity): array
    {
        return $report->toArray(true)['entities'][$entity];
    }

    /** @return list<string> */
    private function skipReasons(ImportReport $report, string $entity): array
    {
        return array_map(static fn (array $row): string => $row['reason'], $this->entity($report, $entity)['skipped_rows']);
    }

    private function token(int $legacyUser): string
    {
        return UserModel::query()->findOrFail($this->newId('user', $legacyUser))->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, string $token, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->json($method, '/api/v1'.$uri, $body);
    }

    /** @return array<string, mixed> the stored item of the code in the summary of the imported job seeker, or an empty list */
    private function seekerItem(int $legacyUser, string $code): array
    {
        $items = $this->api('GET', '/me/import-summary', $this->token($legacyUser))->assertOk()->json('data.items');

        return collect($items)->firstWhere('code', $code) ?? [];
    }

    // ----- saved jobs -----

    public function test_saved_jobs_keep_their_date_and_report_what_cannot_be_kept(): void
    {
        $live = $this->legacyJob();
        $closed = $this->legacyJob('Designer');
        $this->legacyRow('bookmark_candidate_job', ['candidate_id' => $this->siti, 'job_id' => $live, 'created_at' => '2026-01-10 10:00:00']);
        $this->legacyRow('bookmark_candidate_job', ['candidate_id' => $this->siti, 'job_id' => $closed, 'created_at' => '2026-02-10 10:00:00']);
        $this->legacyRow('bookmark_candidate_job', ['candidate_id' => $this->siti, 'job_id' => null, 'created_at' => '2026-02-11 10:00:00']);
        $this->legacyRow('bookmark_candidate_job', ['candidate_id' => $this->siti, 'job_id' => 9999, 'created_at' => '2026-02-12 10:00:00']);
        $this->legacyRow('bookmark_candidate_job', ['candidate_id' => $this->siti, 'job_id' => $live, 'created_at' => '2026-03-01 10:00:00']);
        $this->import('saved-jobs', false);
        $this->assertSame(0, DB::table('job_saved_jobs')->count());
        $this->assertSame([], $this->seekerItem($this->sitiUser, 'saved_jobs_imported'));

        DB::table('job_jobs')->where('id', $this->newId('job', $live))->update(['status' => 'active']);
        DB::table('job_jobs')->where('id', $this->newId('job', $closed))->update(['status' => 'closed']);
        $report = $this->import('saved-jobs');

        $entity = $this->entity($report, 'saved-jobs');
        $this->assertSame([5, 2, 3], [$entity['read'], $entity['imported'], $entity['skipped']]);
        $this->assertEqualsCanonicalizing(['job-missing', 'job-not-imported', 'duplicate'], $this->skipReasons($report, 'saved-jobs'));
        $this->assertSame(1, $entity['notes']['job-not-live']);

        $userId = $this->newId('user', $this->sitiUser);
        $this->assertSame(
            ['2026-01-10 10:00:00', '2026-02-10 10:00:00'],
            DB::table('job_saved_jobs')->where('user_id', $userId)->orderBy('saved_at')->pluck('saved_at')->map(fn ($time): string => substr((string) $time, 0, 19))->all(),
        );

        $imported = $this->seekerItem($this->sitiUser, 'saved_jobs_imported');
        $this->assertEquals([2, ['unavailable' => 1]], [$imported['count'], $imported['detail']]);
        $skipped = $this->seekerItem($this->sitiUser, 'saved_jobs_skipped');
        $this->assertEquals([2, ['job_missing' => 1, 'job_not_imported' => 1]], [$skipped['count'], $skipped['detail']]);

        $this->api('GET', '/me/saved-jobs', $this->token($this->sitiUser))->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_saved_jobs_are_imported_once(): void
    {
        $job = $this->legacyJob();
        $this->legacyRow('bookmark_candidate_job', ['candidate_id' => $this->siti, 'job_id' => $job, 'created_at' => '2026-01-10 10:00:00']);

        $this->import('saved-jobs');
        $report = $this->import('saved-jobs');

        $this->assertSame(1, DB::table('job_saved_jobs')->count());
        $this->assertSame(1, $this->entity($report, 'saved-jobs')['notes']['already-imported']);
        $this->assertSame(0, $this->entity($report, 'saved-jobs')['imported']);
    }

    // ----- job alerts -----

    public function test_one_daily_alert_per_account_with_the_roles_and_the_state_of_the_switch(): void
    {
        $this->legacyRow('candidate_job_alerts', ['candidate_id' => $this->siti, 'job_role_id' => 1]);
        $this->legacyRow('candidate_job_alerts', ['candidate_id' => $this->siti, 'job_role_id' => 2]);
        $this->legacyRow('candidate_job_alerts', ['candidate_id' => $this->siti, 'job_role_id' => 9999]);
        $this->legacyRow('candidate_job_alerts', ['candidate_id' => $this->budi, 'job_role_id' => 1]);
        DB::connection('legacy')->table('candidates')->where('id', $this->budi)->update(['received_job_alert' => 0]);

        $this->import('job-alerts', false);
        $this->assertSame(0, DB::table('jobalert_alerts')->count());

        $report = $this->import('job-alerts');

        $this->assertSame([2, 2, 0], [$this->entity($report, 'job-alerts')['read'], $this->entity($report, 'job-alerts')['imported'], $this->entity($report, 'job-alerts')['skipped']]);
        $this->assertSame(1, $this->entity($report, 'job-alerts')['notes']['role-not-matched']);

        $siti = DB::table('jobalert_alerts')->where('user_id', $this->newId('user', $this->sitiUser))->first();
        $this->assertSame(['Dari karir.pkp', 'daily', true, true, false], [$siti->name, $siti->frequency, (bool) $siti->email, (bool) $siti->in_app, (bool) $siti->paused]);
        $this->assertSame('2026-10-08 09:00:00', substr((string) $siti->last_run_at, 0, 19));
        $this->assertSame(['role_id' => [$this->referenceId('reference_job_roles', 'backend-developer'), $this->referenceId('reference_job_roles', 'frontend-developer')]], json_decode((string) $siti->criteria, true));

        $budi = DB::table('jobalert_alerts')->where('user_id', $this->newId('user', $this->budiUser))->first();
        $this->assertTrue((bool) $budi->paused);

        $item = $this->seekerItem($this->sitiUser, 'job_alert_imported');
        $this->assertEquals([1, ['roles' => 2, 'roles_left_out' => 1]], [$item['count'], $item['detail']]);
        $this->assertEquals(['paused' => 1, 'roles' => 1], $this->seekerItem($this->budiUser, 'job_alert_imported')['detail']);
    }

    public function test_an_account_without_a_matching_role_gets_no_alert_and_a_rerun_adds_nothing(): void
    {
        $this->legacyRow('candidate_job_alerts', ['candidate_id' => $this->siti, 'job_role_id' => 9999]);
        $this->legacyRow('candidate_job_alerts', ['candidate_id' => $this->budi, 'job_role_id' => 1]);

        $first = $this->import('job-alerts');
        $second = $this->import('job-alerts');

        $this->assertSame(['no-role-matched'], $this->skipReasons($first, 'job-alerts'));
        $this->assertSame(1, DB::table('jobalert_alerts')->count());
        $this->assertSame(1, $this->entity($second, 'job-alerts')['notes']['already-imported']);
        $this->assertEquals(['no_role_matched' => 1], $this->seekerItem($this->sitiUser, 'job_alert_skipped')['detail']);
    }

    public function test_the_first_sign_in_moves_the_cursor_of_an_imported_alert(): void
    {
        $this->legacyRow('candidate_job_alerts', ['candidate_id' => $this->siti, 'job_role_id' => 1]);
        $this->import('job-alerts');
        $userId = $this->newId('user', $this->sitiUser);
        UserModel::query()->whereKey($userId)->update(['password' => bcrypt('a-long-enough-password'), 'status' => 'active', 'email_verified_at' => '2026-10-01 00:00:00']);

        Carbon::setTestNow('2026-10-20 09:00:00');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => 'a-long-enough-password'])->assertOk();

        $this->assertSame('2026-10-20 09:00:00', substr((string) DB::table('jobalert_alerts')->where('user_id', $userId)->value('last_run_at'), 0, 19));
    }

    // ----- saved candidates -----

    private function category(string $name, ?int $company = null): int
    {
        return $this->legacyRow('company_bookmark_categories', ['company_id' => $company ?? $this->company, 'name' => $name]);
    }

    private function bookmark(int $candidate, ?int $category = null, string $savedAt = '2025-12-01 10:00:00', ?int $company = null): int
    {
        return $this->legacyRow('bookmark_company', ['company_id' => $company ?? $this->company, 'candidate_id' => $candidate, 'category_id' => $category, 'created_at' => $savedAt]);
    }

    /** @return array<string, mixed> */
    private function pool(): array
    {
        DB::table('company_companies')->update(['status' => 'active']);

        return $this->api('GET', '/company/talent-pool', $this->token($this->ownerUser))->assertOk()->json();
    }

    public function test_a_company_keeps_a_private_list_that_shows_only_the_name_the_category_and_the_date(): void
    {
        $designers = $this->category('Designers');
        $this->category('designers');
        $this->category('');
        $this->bookmark($this->siti, $designers, '2025-12-01 10:00:00');
        $pivoted = $this->bookmark($this->budi, null, '2026-01-05 10:00:00');
        $this->legacyRow('bookmark_company_category', ['bookmark_id' => $pivoted, 'category_id' => $designers]);

        $this->import('saved-candidates', false);
        $this->assertSame(0, DB::table('talentpool_entries')->count());

        $report = $this->import('saved-candidates');

        $this->assertSame([2, 0], [$this->entity($report, 'saved-candidates')['imported'], $this->entity($report, 'saved-candidates')['skipped']]);
        $categories = $this->entity($report, 'saved-candidate-categories');
        $this->assertSame([1, 1, 1], [$categories['imported'], $categories['notes']['same-name-merged'], $categories['skipped']]);
        $this->assertSame(['Designers'], DB::table('talentpool_categories')->pluck('name')->all());

        $companyId = $this->newId('company', $this->company);
        $entries = DB::table('talentpool_entries')->where('company_id', $companyId)->orderBy('id')->get();
        $this->assertSame(['legacy', 'legacy'], $entries->pluck('source')->all());
        $this->assertSame(2, $entries->whereNotNull('category_id')->count());
        $this->assertSame([null, null], [$entries[0]->note, $entries[0]->saved_by_user_id]);
        $this->assertSame('2025-12-01 10:00:00', substr((string) $entries[0]->created_at, 0, 19));
        $this->assertSame('2026-10-08 09:00:00', substr((string) $entries[0]->updated_at, 0, 19));

        $list = $this->pool();
        $this->assertCount(2, $list['data']);
        $siti = collect($list['data'])->firstWhere('candidate.name', 'Siti Rahma');
        $this->assertSame(['legacy', false, null, 'Designers', '2025-12-01T10:00:00+00:00'], [$siti['source'], $siti['available'], $siti['candidate']['slug'], $siti['category']['name'], $siti['saved_at']]);
        $this->assertSame([null, null, null, [], null, null], [$siti['note'], $siti['headline'], $siti['city'], $siti['skills'], $siti['last_application'], $siti['last_invitation']]);

        $detail = $this->api('GET', '/company/talent-pool/'.$siti['id'], $this->token($this->ownerUser))->assertOk();
        $detail->assertJsonPath('data.candidate.name', 'Siti Rahma')->assertJsonPath('data.available', false)->assertJsonPath('data.profile', null);
        $this->assertSame([], $detail->json('data.applications'));
    }

    public function test_the_owners_of_the_lists_are_told_and_a_candidate_not_imported_is_skipped(): void
    {
        $demo = $this->legacyUser(['name' => 'Demo', 'is_demo_field' => 1], ['title' => 'Demo']);
        $this->bookmark($this->siti);
        $this->bookmark($this->legacyCandidateId($demo));

        $report = $this->import('saved-candidates');

        $this->assertSame(['candidate-not-imported'], $this->skipReasons($report, 'saved-candidates'));
        $this->assertSame(1, DB::table('talentpool_entries')->count());

        $summary = $this->api('GET', '/company/import-summary', $this->token($this->ownerUser))->assertOk()->json('data.items');
        $this->assertSame(['saved_candidates_imported' => 1], collect($summary)->whereIn('code', ['saved_candidates_imported'])->pluck('count', 'code')->all());
        $this->assertEquals(['candidate_not_imported' => 1], collect($summary)->firstWhere('code', 'saved_candidates_skipped')['detail']);
        $this->assertSame(1, $this->seekerItem($this->sitiUser, 'saved_by_companies')['count']);
        $this->assertSame([], $this->seekerItem($this->budiUser, 'saved_by_companies'));
    }

    public function test_the_excess_categories_are_dropped_and_their_entries_are_left_uncategorised(): void
    {
        for ($number = 1; $number <= 31; $number++) {
            $last = $this->category("Category {$number}");
        }

        $this->bookmark($this->siti, $last);
        $this->bookmark($this->budi, 1);

        $report = $this->import('saved-candidates');

        $this->assertSame(30, DB::table('talentpool_categories')->count());
        $this->assertSame(['category-limit'], $this->skipReasons($report, 'saved-candidate-categories'));
        $this->assertSame(1, $this->entity($report, 'saved-candidates')['notes']['category-not-carried']);
        $this->assertSame(1, DB::table('talentpool_entries')->whereNull('category_id')->count());
        $summary = $this->api('GET', '/company/import-summary', $this->token($this->ownerUser))->assertOk()->json('data.items');
        $this->assertSame(1, collect($summary)->firstWhere('code', 'saved_candidates_uncategorised')['count']);
    }

    public function test_the_saved_candidates_are_imported_once_and_a_duplicate_collapses(): void
    {
        $this->bookmark($this->siti);
        $this->bookmark($this->siti, null, '2026-02-01 10:00:00');

        $first = $this->import('saved-candidates');
        $second = $this->import('saved-candidates');

        $this->assertSame(1, DB::table('talentpool_entries')->count());
        $this->assertSame(['duplicate'], $this->skipReasons($first, 'saved-candidates'));
        $this->assertSame(1, $this->entity($second, 'saved-candidates')['notes']['already-imported']);
    }

    public function test_a_deactivated_account_is_hidden_from_the_list(): void
    {
        $this->bookmark($this->siti);
        $this->import('saved-candidates');
        $this->assertSame('Siti Rahma', $this->pool()['data'][0]['candidate']['name']);

        UserModel::query()->whereKey($this->newId('user', $this->sitiUser))->update(['status' => 'deactivated']);

        $entry = $this->pool()['data'][0];
        $this->assertSame([false, null], [$entry['available'], $entry['candidate']]);
    }

    public function test_the_candidate_removes_themselves_from_the_lists_of_the_import_only(): void
    {
        $this->bookmark($this->siti);
        $this->import('saved-candidates');
        $userId = $this->newId('user', $this->sitiUser);
        $companyId = $this->newId('company', $this->company);
        $other = DB::table('company_companies')->insertGetId([
            'name' => 'PT Lain', 'slug' => 'pt-lain', 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('talentpool_entries')->insert(['company_id' => $other, 'candidate_user_id' => $userId, 'source' => 'search', 'created_at' => now(), 'updated_at' => now()]);
        $token = $this->token($this->sitiUser);

        $this->api('DELETE', '/me/talent-pool-memberships', $token)->assertStatus(422);
        $this->api('DELETE', '/me/talent-pool-memberships?source=search', $token)->assertStatus(422);
        $this->assertSame(2, DB::table('talentpool_entries')->count());

        $this->api('DELETE', '/me/talent-pool-memberships?source=legacy', $token)->assertNoContent();
        $this->api('DELETE', '/me/talent-pool-memberships?source=legacy', $token)->assertNoContent();

        $this->assertSame(0, DB::table('talentpool_entries')->where('company_id', $companyId)->count());
        $this->assertSame(1, DB::table('talentpool_entries')->where('company_id', $other)->count());
    }

    public function test_only_a_job_seeker_leaves_the_lists(): void
    {
        $this->import('saved-candidates');

        $this->api('DELETE', '/me/talent-pool-memberships?source=legacy', $this->token($this->ownerUser))->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->deleteJson('/api/v1/me/talent-pool-memberships?source=legacy')->assertUnauthorized();
    }

    public function test_an_entry_of_the_import_is_deleted_24_months_after_it_when_nothing_else_keeps_it(): void
    {
        $this->bookmark($this->siti);
        $this->bookmark($this->budi);
        $this->import('saved-candidates');
        $old = $this->newId('saved_candidate', 1);
        DB::table('talentpool_entries')->where('id', $old)->update(['updated_at' => '2024-10-07 09:00:00']);

        Artisan::call('talentpool:purge-lapsed-consent');

        $this->assertSame(1, DB::table('talentpool_entries')->count());
        $this->assertSame(0, DB::table('talentpool_entries')->where('id', $old)->count());

        Carbon::setTestNow('2028-10-09 09:00:00');
        Artisan::call('talentpool:purge-lapsed-consent');

        $this->assertSame(0, DB::table('talentpool_entries')->count());
    }
}
