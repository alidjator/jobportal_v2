<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\LegacyImport\Application\ImportReport;
use Src\LegacyImport\Application\ImportRunner;
use Tests\Concerns\CreatesReferenceRows;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class CompaniesImporterTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    private int $industry;

    private int $organizationType;

    private int $teamSize;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 09:00:00');
        $this->setUpLegacyDatabase();

        $this->industry = $this->industryId();
        $this->organizationType = $this->organizationTypeId();
        $this->teamSize = $this->referenceId('reference_team_sizes', 'small', ['min_size' => 1, 'max_size' => 10]);
        DB::table('reference_team_sizes')->where('id', $this->teamSize)->update(['name' => json_encode(['id' => '1-10 orang', 'en' => '1-10 people'])]);

        $this->legacyMasterItem('industry_types', 1, ['en' => 'Technology']);
        $this->legacyMasterItem('industry_types', 2, ['en' => 'Underwater Basket Weaving']);
        $this->legacyMasterItem('organization_types', 1, ['en' => 'Private Company']);
        $this->legacyMasterItem('team_sizes', 1, ['en' => '1-10 people']);
        $this->legacyMasterItem('team_sizes', 2, ['en' => 'A crowd']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function import(bool $write = true): ImportReport
    {
        $runner = $this->app->make(ImportRunner::class);
        $runner->run('masters', true);
        $runner->run('accounts', true);

        return $runner->run('companies', $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report): array
    {
        return $report->toArray(true)['entities']['companies'];
    }

    /** A legacy company owner (an account that is imported with the company). */
    private function owner(string $name = 'PT Maju Jaya', array $company = [], int $industry = 1): int
    {
        $user = $this->legacyUser(['name' => $name, 'role' => 'company']);
        $this->legacyCompany($user, $industry, 1, $company);

        return $user;
    }

    private function company(): object
    {
        return DB::table('company_companies')->first();
    }

    // ----- what is imported and how -----

    public function test_a_company_is_imported_with_its_owner_as_the_company_admin(): void
    {
        $user = $this->owner('PT Maju Jaya', [
            'team_size_id' => 1, 'website' => 'https://majujaya.example.test', 'establishment_date' => '2015-06-01', 'visibility' => 1,
            'bio' => '<p>We build <b>things</b>.</p>', 'vision' => '<p>A better world</p>', 'address' => 'Jl. Merdeka 1',
            'created_at' => '2023-01-02 03:04:05', 'updated_at' => '2023-02-03 04:05:06',
        ]);

        $report = $this->import();

        $company = $this->company();
        $this->assertSame('PT Maju Jaya', $company->name);
        $this->assertSame('pt-maju-jaya', $company->slug);
        $this->assertSame('active', $company->status);
        $this->assertSame('https://majujaya.example.test', $company->website);
        $this->assertSame('2015-06-01', $company->established_on);
        $this->assertSame('We build things.', $company->bio);
        $this->assertSame('A better world', $company->vision);
        $this->assertSame($this->industry, (int) $company->industry_id);
        $this->assertSame($this->organizationType, (int) $company->organization_type_id);
        $this->assertSame($this->teamSize, (int) $company->team_size_id);
        $this->assertSame('Jl. Merdeka 1', $company->address_line);
        $this->assertSame(1, (int) $company->is_visible);
        $this->assertNull($company->verified_at);
        $this->assertNull($company->logo_path);
        $this->assertNull($company->contact_email);
        $this->assertSame('2023-01-02 03:04:05', $company->created_at);
        $this->assertSame('2023-02-03 04:05:06', $company->updated_at);

        $newOwner = (int) DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $user)->value('new_id');
        $member = DB::table('company_members')->first();
        $this->assertSame([(int) $company->id, $newOwner, 'company_admin'], [(int) $member->company_id, (int) $member->user_id, $member->role]);
        $this->assertSame((int) $company->id, (int) DB::table('legacyimport_id_map')->where('entity', 'company')->value('new_id'));
        $this->assertSame(1, $this->entity($report)['imported']);
    }

    public function test_a_company_keeps_its_badge_from_the_legacy_time_or_flag(): void
    {
        $this->owner('PT Satu', ['document_verified_at' => '2023-05-06 07:08:09']);
        $this->owner('PT Dua', ['is_profile_verified' => 1]);
        $this->owner('PT Tiga');

        $this->import();

        $badges = DB::table('company_companies')->orderBy('id')->pluck('verified_at', 'name')->all();
        $this->assertSame(['PT Satu' => '2023-05-06 07:08:09', 'PT Dua' => '2026-10-07 09:00:00', 'PT Tiga' => null], $badges);
        $this->assertSame(0, DB::table('company_documents')->count());
    }

    public function test_the_location_is_kept_as_far_as_the_chain_is_valid(): void
    {
        $chain = $this->locationChain();
        $this->owner('PT Lengkap', ['region' => '32', 'district' => '3273', 'subdistrict' => 327301, 'kelurahan' => 3273011001, 'postcode' => '40153']);
        $this->owner('PT Terpotong', ['region' => '32', 'district' => '9999', 'subdistrict' => 327301, 'kelurahan' => 3273011001, 'postcode' => '40153']);
        $this->owner('PT Tanpa', ['region' => '99']);

        $this->import();

        $rows = DB::table('company_companies')->orderBy('id')->get(['province_id', 'city_id', 'subdistrict_id', 'village_id', 'postal_code_id'])->map(fn ($row): array => array_map(fn ($id): ?int => $id === null ? null : (int) $id, (array) $row))->all();
        $this->assertSame([$chain['province_id'], $chain['city_id'], $chain['subdistrict_id'], $chain['village_id'], $chain['postal_code_id']], array_values($rows[0]));
        $this->assertSame([$chain['province_id'], null, null, null, null], array_values($rows[1]));
        $this->assertSame([null, null, null, null, null], array_values($rows[2]));
    }

    public function test_an_invalid_website_date_or_team_size_is_emptied_with_a_note(): void
    {
        $this->owner('PT Salah', ['team_size_id' => 2, 'website' => 'not a url', 'establishment_date' => '2999-01-01']);

        $report = $this->entity($this->import());

        $company = $this->company();
        $this->assertNull($company->website);
        $this->assertNull($company->established_on);
        $this->assertNull($company->team_size_id);
        $this->assertSame(['establishment-date-invalid' => 1, 'team-size-unmatched' => 1, 'website-invalid' => 1], collect($report['notes'])->sortKeys()->all());
        $this->assertSame(1, $report['imported']);
    }

    public function test_text_is_made_plain_and_cut_to_the_limit(): void
    {
        $this->owner('PT Panjang', ['bio' => '<div>'.str_repeat('a', 6000).'</div>']);

        $this->import();

        $company = $this->company();
        $this->assertSame(5000, mb_strlen($company->bio));
    }

    // ----- the slug -----

    public function test_slugs_are_unique_valid_and_never_reserved(): void
    {
        $this->owner('PT Maju');
        $this->owner('PT  Maju!');
        $this->owner('Admin');
        $third = $this->owner('AB');
        $this->owner('Café Nusantara');

        $this->import();

        $companyOfThird = (int) DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $third)->value('new_id');
        $this->assertSame(
            ['pt-maju', 'pt-maju-2', 'company-3', 'company-4', 'cafe-nusantara'],
            DB::table('company_companies')->orderBy('id')->pluck('slug')->all(),
        );
        $this->assertGreaterThan(0, $companyOfThird);
    }

    // ----- what is skipped -----

    public function test_a_company_without_its_owner_or_a_master_is_skipped_with_the_reason(): void
    {
        $demoOwner = $this->legacyUser(['role' => 'company', 'is_demo_field' => 1]);
        $demoCompany = $this->legacyCompany($demoOwner, 1, 1);
        $user = $this->legacyUser(['role' => 'company']);
        $noIndustry = $this->legacyCompany($user, 2, 1);
        $user2 = $this->legacyUser(['role' => 'company']);
        $noOrg = $this->legacyCompany($user2, 1, 77);
        $this->owner('PT Baik');

        $report = $this->entity($this->import());

        $reasons = collect($report['skipped_rows'])->pluck('reason', 'legacy_id')->all();
        $this->assertSame([$demoCompany => 'owner-not-imported', $noIndustry => 'industry-unmatched', $noOrg => 'organization-type-unmatched'], $reasons);
        $this->assertSame(1, DB::table('company_companies')->count());
    }

    // ----- safe to repeat, and a dry run -----

    public function test_running_it_again_adds_nothing_and_never_overwrites(): void
    {
        $this->owner('PT Maju');
        $this->import();
        DB::table('company_companies')->update(['bio' => 'Edited in the new system']);
        $this->owner('PT Baru');

        $report = $this->entity($this->import());

        $this->assertSame(2, DB::table('company_companies')->count());
        $this->assertSame(['Edited in the new system', null], DB::table('company_companies')->orderBy('id')->pluck('bio')->all());
        $this->assertSame(1, $report['notes']['already-imported']);
        $this->assertSame(2, DB::table('company_members')->count());
    }

    public function test_a_dry_run_reports_but_writes_nothing(): void
    {
        $this->owner('PT Maju');

        $report = $this->entity($this->import(false));

        $this->assertSame(['read' => 1, 'imported' => 1, 'skipped' => 0], array_intersect_key($report, array_flip(['read', 'imported', 'skipped'])));
        $this->assertSame(0, DB::table('company_companies')->count());
        $this->assertSame(0, DB::table('company_members')->count());
        $this->assertSame(0, DB::table('legacyimport_id_map')->where('entity', 'company')->count());
    }

    // ----- the result seen through the API -----

    public function test_the_company_is_public_by_its_slug_and_its_owner_runs_it(): void
    {
        $this->owner('PT Maju Jaya', ['bio' => '<p>Hello</p>', 'is_profile_verified' => 1]);
        $this->import();

        $this->getJson('/api/v1/companies/pt-maju-jaya')->assertOk()->assertJsonPath('data.name', 'PT Maju Jaya')->assertJsonPath('data.verified', true);

        $user = UserModel::query()->where('type', 'company_member')->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();
        $token = $user->createToken('device', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/company/me')->assertOk()->assertJsonPath('data.role', 'company_admin')->assertJsonPath('data.company.slug', 'pt-maju-jaya');
    }
}
