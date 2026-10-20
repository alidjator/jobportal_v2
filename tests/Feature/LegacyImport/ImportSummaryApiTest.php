<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Identity\Application\IdentitySettings;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\JobSeeker\Application\EraseJobSeekerData;
use Src\JobSeeker\Application\ImportSummary\ImportSummaryDataExporter;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-activation.md` section 5: reading, dismissing, purging, exporting and erasing the import summaries. */
final class ImportSummaryApiTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    private const array ITEMS = [
        ['code' => 'password_reset_needed', 'count' => 1],
        ['code' => 'resume_skipped', 'count' => 2, 'detail' => ['not-a-pdf' => 1, 'too-large' => 1]],
    ];

    private int $seekerId;

    private string $seekerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
        $this->seekerId = $this->seeker('siti@example.test');
        $this->seekerToken = $this->tokenFor($this->seekerId);
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    private function seeker(string $email, array $attributes = []): int
    {
        return (int) UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00', ...$attributes])->id;
    }

    private function seekerSummary(int $userId, array $items = self::ITEMS): void
    {
        DB::table('jobseeker_import_summaries')->insert(['user_id' => $userId, 'items' => json_encode($items), 'created_at' => '2026-10-30 12:00:00']);
    }

    private function companySummary(int $companyId, array $items = self::ITEMS): void
    {
        DB::table('company_import_summaries')->insert(['company_id' => $companyId, 'items' => json_encode($items), 'created_at' => '2026-10-30 12:00:00']);
    }

    private function setting(string $key, mixed $value): void
    {
        $this->app->make(SettingsStore::class)->setGlobal($key, $value, null);
    }

    // ----- job seeker -----

    public function test_a_job_seeker_without_a_summary_gets_404_with_its_own_code(): void
    {
        $this->api('GET', '/me/import-summary', token: $this->seekerToken)->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:no-import-summary');
        $this->api('POST', '/me/import-summary/dismiss', token: $this->seekerToken)->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:no-import-summary');
    }

    public function test_a_job_seeker_reads_their_summary_with_the_static_notes(): void
    {
        $this->seekerSummary($this->seekerId);
        $this->seekerSummary($this->seeker('other@example.test'), [['code' => 'avatar_skipped', 'count' => 1]]);

        $response = $this->api('GET', '/me/import-summary', token: $this->seekerToken)->assertOk();

        $response->assertJsonPath('data.kind', 'job_seeker')
            ->assertJsonPath('data.imported_at', '2026-10-30T12:00:00+00:00')
            ->assertJsonPath('data.dismissed_at', null)
            ->assertJsonPath('data.items.1.detail.too-large', 1);
        $this->assertSame(['password_reset_needed', 'resume_skipped'], array_column($response->json('data.items'), 'code'));
        $this->assertContains('terms_again', $response->json('data.notes'));
        $this->assertContains('chat_history_not_imported', $response->json('data.notes'));
    }

    public function test_dismissing_keeps_the_list_and_the_first_time(): void
    {
        $this->seekerSummary($this->seekerId);

        $first = now()->utc()->format('Y-m-d\TH:i:sP');
        $this->api('POST', '/me/import-summary/dismiss', token: $this->seekerToken)->assertNoContent();
        Carbon::setTestNow(now()->addHours(2));
        $this->api('POST', '/me/import-summary/dismiss', token: $this->seekerToken)->assertNoContent();

        $this->api('GET', '/me/import-summary', token: $this->seekerToken)->assertOk()
            ->assertJsonPath('data.dismissed_at', $first)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_only_a_job_seeker_uses_the_job_seeker_routes(): void
    {
        $this->api('GET', '/me/import-summary', token: $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/v1/me/import-summary')->assertUnauthorized();
    }

    public function test_get_me_tells_whether_the_account_came_from_karir_pkp(): void
    {
        $this->api('GET', '/me', token: $this->seekerToken)->assertOk()->assertJsonPath('data.imported_from_legacy', false);

        DB::table('identity_users')->where('id', $this->seekerId)->update(['imported_at' => '2026-10-30 00:00:00']);

        $this->api('GET', '/me', token: $this->seekerToken)->assertOk()->assertJsonPath('data.imported_from_legacy', true);
    }

    // ----- company -----

    public function test_a_company_admin_reads_and_dismisses_the_company_summary(): void
    {
        $this->companySummary($this->companyId);

        $this->api('GET', '/company/import-summary', token: $this->adminToken)->assertOk()
            ->assertJsonPath('data.kind', 'company')
            ->assertJsonPath('data.items.0.code', 'password_reset_needed')
            ->assertJsonPath('data.dismissed_at', null);
        $this->assertContains('team_members_not_carried', $this->api('GET', '/company/import-summary', token: $this->adminToken)->json('data.notes'));

        $this->api('POST', '/company/import-summary/dismiss', token: $this->adminToken)->assertNoContent();

        $row = DB::table('company_import_summaries')->where('company_id', $this->companyId)->first();
        $this->assertSame(now()->utc()->format('Y-m-d H:i:s'), $row->dismissed_at);
        $this->assertSame($this->adminId, (int) $row->dismissed_by_user_id);
    }

    public function test_a_company_without_a_summary_gets_404_and_never_sees_another_companys(): void
    {
        $this->companySummary($this->companyId);

        $this->api('GET', '/company/import-summary', token: $this->otherAdminToken)->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:no-import-summary');
        $this->api('POST', '/company/import-summary/dismiss', token: $this->otherAdminToken)->assertNotFound();
        $this->assertNull(DB::table('company_import_summaries')->where('company_id', $this->companyId)->value('dismissed_at'));
    }

    public function test_a_member_who_is_not_a_company_admin_and_a_job_seeker_are_refused(): void
    {
        $this->companySummary($this->companyId);

        $this->api('GET', '/company/import-summary', token: $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-admin-required');
        $this->api('POST', '/company/import-summary/dismiss', token: $this->recruiterToken)->assertForbidden();
        $this->api('GET', '/company/import-summary', token: $this->seekerToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
    }

    // ----- retention -----

    public function test_the_nightly_purge_deletes_a_job_seeker_summary_90_days_after_the_first_sign_in_only(): void
    {
        $old = $this->seeker('old@example.test', ['first_login_at' => now()->subDays(100)]);
        $recent = $this->seeker('recent@example.test', ['first_login_at' => now()->subDays(10)]);
        $never = $this->seeker('never@example.test');

        foreach ([$old, $recent, $never] as $id) {
            $this->seekerSummary($id);
        }

        $this->assertSame(0, Artisan::call('job-seeker:purge-import-summaries'));

        $this->assertSame([$recent, $never], DB::table('jobseeker_import_summaries')->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all());

        $this->setting(IdentitySettings::IMPORT_SUMMARY_RETENTION_DAYS, 7);
        Artisan::call('job-seeker:purge-import-summaries');

        $this->assertSame([$never], DB::table('jobseeker_import_summaries')->pluck('user_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_the_company_purge_follows_the_first_sign_in_of_the_owner(): void
    {
        $members = $this->app->make(CompanyMemberRepository::class);
        $members->setOwner($this->companyId, $this->adminId);
        $members->setOwner($this->otherCompanyId, $this->otherAdminId);
        DB::table('identity_users')->where('id', $this->adminId)->update(['first_login_at' => now()->subDays(100)]);
        $this->companySummary($this->companyId);
        $this->companySummary($this->otherCompanyId);

        $this->assertSame(0, Artisan::call('company:purge-import-summaries'));

        $this->assertSame([$this->otherCompanyId], DB::table('company_import_summaries')->pluck('company_id')->map(fn ($id) => (int) $id)->all());
    }

    // ----- erasure and export -----

    public function test_the_summary_is_in_the_data_export_and_erasing_the_account_deletes_it(): void
    {
        $this->seekerSummary($this->seekerId);

        $section = $this->app->make(ImportSummaryDataExporter::class)->export($this->seekerId);

        $this->assertSame(['password_reset_needed', 'resume_skipped'], array_column($section->data[0]['items'], 'code'));
        $this->assertSame([], $this->app->make(ImportSummaryDataExporter::class)->export($this->seeker('none@example.test'))->data);

        $this->app->make(EraseJobSeekerData::class)->execute($this->seekerId);

        $this->assertSame(0, DB::table('jobseeker_import_summaries')->where('user_id', $this->seekerId)->count());
    }
}
