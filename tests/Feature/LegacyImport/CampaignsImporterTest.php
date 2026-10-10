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

final class CampaignsImporterTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    private int $owner;

    private int $company;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 09:00:00');
        $this->setUpLegacyDatabase();
        $this->locationChain();
        $this->industryId();
        $this->organizationTypeId();
        $this->legacyMasterItem('industry_types', 1, ['en' => 'technology']);
        $this->legacyMasterItem('organization_types', 1, ['en' => 'private-company']);

        $this->owner = $this->legacyUser(['name' => 'PT Maju Jaya', 'role' => 'company']);
        $this->company = $this->legacyCompany($this->owner, 1, 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function import(bool $write = true): ImportReport
    {
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'companies'] as $step) {
            $runner->run($step, true);
        }

        return $runner->run('campaigns', $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report): array
    {
        return $report->toArray(true)['entities']['campaigns'];
    }

    /** @return list<string> */
    private function skipReasons(ImportReport $report): array
    {
        return array_map(static fn (array $row): string => $row['reason'], $this->entity($report)['skipped_rows']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function campaign(string $slug, array $attributes = []): int
    {
        return $this->legacyRow('campaigns', [
            'company_id' => $this->company, 'title' => 'Job Fair Kampus', 'slug' => $slug, 'campaign_type' => 'job_fair',
            'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'created_by' => $this->owner,
            'created_at' => '2026-09-01 08:00:00', 'updated_at' => '2026-09-02 09:00:00', ...$attributes,
        ]);
    }

    public function test_a_campaign_is_imported_with_its_code_type_period_and_creator(): void
    {
        $legacy = $this->campaign('Cmp-JF-Kampus', ['campaign_type' => 'lembaga_pendidikan']);

        $report = $this->import();

        $this->assertSame(['read' => 1, 'imported' => 1, 'skipped' => 0], array_intersect_key($this->entity($report), array_flip(['read', 'imported', 'skipped'])));

        $row = DB::table('campaign_campaigns')->first();
        $this->assertSame('cmp-jf-kampus', $row->code);
        $this->assertSame('Job Fair Kampus', $row->name);
        $this->assertSame('education_institution', $row->type);
        $this->assertSame('2026-10-01', $row->starts_on);
        $this->assertSame('2026-12-31', $row->ends_on);
        $this->assertTrue((bool) $row->is_active);
        $this->assertNull($row->created_by_admin_id);
        $this->assertSame('2026-09-01 08:00:00', $row->created_at);
        $this->assertSame('2026-09-02 09:00:00', $row->updated_at);
        $this->assertSame((int) $row->id, $this->app->make(\Src\LegacyImport\Domain\IdMap::class)->find('campaign', $legacy));
        $this->assertSame((int) DB::table('company_companies')->value('id'), (int) $row->company_id);
        $this->assertSame((int) UserModel::query()->where('email', 'like', 'legacy%')->value('id'), (int) $row->created_by_user_id);

        $this->getJson('/api/v1/campaigns/Cmp-JF-Kampus')->assertOk()->assertJsonPath('data.name', 'Job Fair Kampus')->assertJsonPath('data.type', 'education_institution');
    }

    public function test_the_company_owner_is_the_creator_when_the_legacy_creator_is_missing(): void
    {
        $this->campaign('cmp-jf-a', ['created_by' => null]);
        $this->campaign('cmp-jf-b', ['created_by' => 9999]);

        $this->import();

        $this->assertSame(2, DB::table('campaign_campaigns')->whereNotNull('created_by_user_id')->count());
    }

    public function test_open_ended_periods_and_a_missing_end_are_kept_open(): void
    {
        $this->campaign('cmp-jf-open', ['start_date' => null, 'end_date' => null]);

        $this->import();

        $row = DB::table('campaign_campaigns')->first();
        $this->assertNull($row->starts_on);
        $this->assertNull($row->ends_on);
    }

    public function test_a_slug_with_other_characters_is_normalised_and_flagged(): void
    {
        $legacy = $this->campaign('Cmp JF  Kampus 2026!');

        $report = $this->import();

        $this->assertSame('cmp-jf-kampus-2026', DB::table('campaign_campaigns')->value('code'));
        $this->assertSame([['legacy_id' => $legacy, 'reason' => 'code-normalised']], $this->entity($report)['flagged_rows']);
    }

    public function test_a_row_that_cannot_be_imported_is_skipped_with_its_reason(): void
    {
        $this->campaign('cmp-ok');
        $noCompany = $this->campaign('cmp-no-company', ['company_id' => null]);
        $unknownCompany = $this->campaign('cmp-unknown-company', ['company_id' => 9999]);
        $type = $this->campaign('cmp-type', ['campaign_type' => 'radio']);
        $name = $this->campaign('cmp-name', ['title' => '  ']);
        $slug = $this->campaign('', []);
        $nullSlug = $this->campaign('cmp-x', ['slug' => null]);
        $unusable = $this->campaign('!!!');
        $period = $this->campaign('cmp-period', ['start_date' => '2026-12-31', 'end_date' => '2026-10-01']);

        $report = $this->import();

        $skipped = collect($this->entity($report)['skipped_rows'])->pluck('reason', 'legacy_id')->all();
        $this->assertSame([
            $noCompany => 'company-missing',
            $unknownCompany => 'company-not-imported',
            $type => 'type-unknown',
            $name => 'name-missing',
            $slug => 'slug-missing',
            $nullSlug => 'slug-missing',
            $unusable => 'slug-unusable',
            $period => 'period-invalid',
        ], $skipped);
        $this->assertSame(1, DB::table('campaign_campaigns')->count());
    }

    public function test_a_code_another_campaign_has_is_skipped_and_the_oldest_row_wins(): void
    {
        $first = $this->campaign('Cmp-Same');
        $second = $this->campaign('cmp-same');

        $report = $this->import();

        $this->assertSame(['code-taken'], $this->skipReasons($report));
        $this->assertSame($second, $this->entity($report)['skipped_rows'][0]['legacy_id']);
        $this->assertSame(1, DB::table('campaign_campaigns')->count());
        $this->assertNotNull($this->app->make(\Src\LegacyImport\Domain\IdMap::class)->find('campaign', $first));
    }

    public function test_a_code_already_in_the_new_system_is_not_taken_again(): void
    {
        $this->campaign('cmp-jf-kampus');
        $this->import();
        $this->campaign('CMP-JF-KAMPUS', ['title' => 'Another']);

        $report = $this->app->make(ImportRunner::class)->run('campaigns', true)['report'];

        $this->assertSame(['code-taken'], $this->skipReasons($report));
        $this->assertSame(1, DB::table('campaign_campaigns')->count());
    }

    public function test_running_again_adds_nothing_and_a_dry_run_writes_nothing(): void
    {
        $this->campaign('cmp-jf-a');

        $this->import(false);
        $this->assertSame(0, DB::table('campaign_campaigns')->count());

        $this->import();
        $report = $this->app->make(ImportRunner::class)->run('campaigns', true)['report'];

        $this->assertSame(1, DB::table('campaign_campaigns')->count());
        $this->assertSame(['already-imported' => 1], $this->entity($report)['notes']);
        $this->assertSame(0, $this->entity($report)['imported']);
    }

    public function test_the_registrations_of_imported_accounts_count_for_the_imported_campaign(): void
    {
        $this->campaign('Cmp-JF-Kampus');
        $this->legacyUser(['email' => 'seeker@example.test'], ['campaign_channel' => 'Cmp-JF-Kampus']);

        $this->import();

        $this->assertSame(1, DB::table('identity_users')->whereRaw('lower(registration_campaign_code) = ?', [DB::table('campaign_campaigns')->value('code')])->count());
    }
}
