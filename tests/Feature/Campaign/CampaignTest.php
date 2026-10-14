<?php

namespace Tests\Feature\Campaign;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Campaign\Domain\Events\CampaignChanged;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class CampaignTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use CreatesReferenceRows;

    private const string LINK = 'https://app.example.test/{code}/register';

    private string $platformToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        config(['app.campaign_registration_url' => self::LINK]);
        $this->platformToken = $this->adminToken(['campaigns.manage']);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email = 'ops@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    /** @param  array<string, mixed>  $overrides */
    private function body(string $code = 'Kampus-2026', array $overrides = []): array
    {
        return ['code' => $code, 'name' => 'Campus tour', 'type' => 'education_institution', 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-31', ...$overrides];
    }

    private function create(array $body, ?string $token = null): TestResponse
    {
        return $this->api('POST', '/company/campaigns', $body, $token ?? $this->adminToken);
    }

    private function ownCampaign(string $code = 'kampus-2026', array $overrides = []): int
    {
        return $this->campaignRowId($code, ['company_id' => $this->companyId, 'created_by_admin_id' => null, 'created_by_user_id' => $this->adminId, ...$overrides]);
    }

    // ----- company -----

    public function test_a_company_admin_creates_a_campaign_of_their_company(): void
    {
        Event::fake([CampaignChanged::class]);

        $data = $this->create($this->body('Kampus-2026', ['company_id' => $this->otherCompanyId]))->assertCreated()
            ->assertJsonPath('data.code', 'kampus-2026')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.link', 'https://app.example.test/kampus-2026/register')
            ->json('data');

        $this->assertSame($this->companyId, $data['company']['id']);

        $row = DB::table('campaign_campaigns')->where('id', $data['id'])->first();
        $this->assertSame($this->companyId, (int) $row->company_id);
        $this->assertSame($this->adminId, (int) $row->created_by_user_id);
        $this->assertNull($row->created_by_admin_id);
        Event::assertDispatched(CampaignChanged::class, fn (CampaignChanged $event) => $event->change === 'created' && $event->actorKind === 'user' && $event->actorId === $this->adminId);
    }

    public function test_the_link_is_missing_while_the_template_is_not_configured(): void
    {
        config(['app.campaign_registration_url' => null]);

        $this->create($this->body())->assertCreated()->assertJsonPath('data.link', null);
    }

    public function test_a_code_is_unique_whatever_its_case_and_must_have_the_right_shape(): void
    {
        $this->create($this->body('Kampus-2026'))->assertCreated();

        $this->create($this->body('KAMPUS-2026'))->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:campaign-code-taken');
        $this->create($this->body('ab'))->assertStatus(422)->assertJsonValidationErrors(['code'], 'errors');
        $this->create($this->body('bad code!'))->assertStatus(422)->assertJsonValidationErrors(['code'], 'errors');
        $this->create($this->body('other', ['type' => 'party']))->assertStatus(422)->assertJsonValidationErrors(['type'], 'errors');
        $this->create($this->body('other', ['starts_on' => '2026-12-01', 'ends_on' => '2026-11-01']))->assertStatus(422)->assertJsonValidationErrors(['ends_on'], 'errors');
    }

    public function test_only_a_company_admin_of_an_active_company_may_use_campaigns(): void
    {
        $id = $this->ownCampaign();

        $this->api('GET', '/company/campaigns', [], $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-permission-required');
        $this->api('GET', '/company/campaigns', [], $this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
        $this->api('GET', "/company/campaigns/{$id}", [], $this->otherAdminToken)->assertNotFound();

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);
        $this->api('GET', "/company/campaigns/{$id}", [], $this->adminToken)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
    }

    public function test_a_company_sees_only_its_own_campaigns_and_can_filter_them(): void
    {
        $mine = $this->ownCampaign('mine-active');
        $ended = $this->ownCampaign('mine-ended', ['ends_on' => '2020-01-01', 'type' => 'partner']);
        $off = $this->ownCampaign('mine-off', ['is_active' => false]);
        $this->ownCampaign('mine-later', ['starts_on' => '2099-01-01']);
        $this->campaignRowId('platform-one');
        $this->campaignRowId('other-company', ['company_id' => $this->otherCompanyId]);

        $ids = fn (string $query = '') => array_column($this->api('GET', '/company/campaigns'.$query, [], $this->adminToken)->assertOk()->json('data'), 'id');

        $this->assertCount(4, $ids());
        $this->assertSame([$mine], $ids('?filter[status]=active'));
        $this->assertSame([$ended], $ids('?filter[status]=ended'));
        $this->assertSame([$off], $ids('?filter[status]=inactive'));
        $this->assertCount(1, $ids('?filter[status]=scheduled'));
        $this->assertSame([$ended], $ids('?filter[type]=partner'));
        $this->assertSame([$mine], $ids('?filter[q]=ACTIVE'));
        // The company filter is for platform admins; a company admin always gets their own company's campaigns.
        $this->assertCount(4, $ids('?filter[company_id]='.$this->otherCompanyId));
        $this->api('GET', '/company/campaigns?filter[owner]=x', [], $this->adminToken)->assertStatus(422);
    }

    public function test_a_campaign_is_changed_but_its_code_and_company_are_not(): void
    {
        Event::fake([CampaignChanged::class]);
        $id = $this->ownCampaign();

        $body = ['name' => 'Renamed', 'type' => 'partner', 'starts_on' => null, 'ends_on' => '2027-01-31', 'is_active' => false];
        $this->api('PUT', "/company/campaigns/{$id}", $body, $this->adminToken)->assertOk()
            ->assertJsonPath('data.code', 'kampus-2026')
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.type', 'partner')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.starts_on', null)
            ->assertJsonPath('data.ends_on', '2027-01-31');
        $this->api('PUT', "/company/campaigns/{$id}", ['code' => 'new-code', 'company_id' => $this->otherCompanyId, ...$body], $this->adminToken)->assertStatus(422)->assertJsonValidationErrors(['code', 'company_id'], 'errors');
        $this->api('PUT', "/company/campaigns/{$id}", $body, $this->otherAdminToken)->assertNotFound();
        Event::assertDispatched(CampaignChanged::class, fn (CampaignChanged $event) => $event->change === 'updated' && $event->campaignId === $id);
    }

    public function test_a_campaign_without_registrations_is_deleted_and_one_with_registrations_is_kept(): void
    {
        Event::fake([CampaignChanged::class]);
        $empty = $this->ownCampaign('empty-one');
        $used = $this->ownCampaign('used-one');
        UserModel::query()->whereKey($this->seekerId)->update(['registration_campaign_code' => 'Used-One']);

        $this->api('DELETE', "/company/campaigns/{$used}", [], $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:campaign-has-registrations');
        $this->api('DELETE', "/company/campaigns/{$empty}", [], $this->otherAdminToken)->assertNotFound();
        $this->api('DELETE', "/company/campaigns/{$empty}", [], $this->adminToken)->assertNoContent();

        $this->assertNull(DB::table('campaign_campaigns')->where('id', $empty)->first());
        $this->assertNotNull(DB::table('campaign_campaigns')->where('id', $used)->first());
        Event::assertDispatched(CampaignChanged::class, fn (CampaignChanged $event) => $event->change === 'deleted' && $event->campaignId === $empty);
    }

    // ----- results and QR -----

    public function test_the_results_are_counts_of_registrants_and_of_their_applications_to_the_company(): void
    {
        $id = $this->ownCampaign();
        $other = $this->ownCampaign('another-one');
        $second = $this->seeker('cakra@example.test');
        $third = $this->seeker('dewi@example.test');
        UserModel::query()->whereKey($this->seekerId)->update(['registration_campaign_code' => 'kampus-2026']);
        UserModel::query()->whereKey($second)->update(['registration_campaign_code' => 'KAMPUS-2026']);
        UserModel::query()->whereKey($third)->update(['registration_campaign_code' => 'another-one']);

        $job = $this->liveJob();
        $this->apply($job)->assertCreated();

        $results = $this->api('GET', "/company/campaigns/{$id}/results", [], $this->adminToken)->assertOk()->json('data');

        $this->assertSame(2, $results['registrations']);
        $this->assertSame(1, $results['applicants']);
        $this->assertSame(1, $results['applications']);
        $this->assertCount(30, $results['registrations_daily']);
        $this->assertSame(2, $results['registrations_daily'][29]['count']);
        $this->assertSame($results['to'], $results['registrations_daily'][29]['day']);
        $this->assertSame(['registrations' => 1, 'applicants' => 0, 'applications' => 0], array_intersect_key($this->api('GET', "/company/campaigns/{$other}/results", [], $this->adminToken)->json('data'), array_flip(['registrations', 'applicants', 'applications'])));
        $this->api('GET', "/company/campaigns/{$id}/results", [], $this->otherAdminToken)->assertNotFound();
        $this->assertArrayNotHasKey('users', $results);
    }

    public function test_the_qr_code_is_an_svg_of_the_registration_link(): void
    {
        $id = $this->ownCampaign();

        $response = $this->api('GET', "/company/campaigns/{$id}/qr", [], $this->adminToken)->assertOk();

        $this->assertStringStartsWith('image/svg+xml', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());

        config(['app.campaign_registration_url' => null]);
        $this->api('GET', "/company/campaigns/{$id}/qr", [], $this->adminToken)->assertStatus(503)->assertJsonPath('type', 'urn:jobportal:error:campaign-link-unavailable');
        $this->api('GET', "/company/campaigns/{$id}/qr", [], $this->otherAdminToken)->assertNotFound();
    }

    // ----- platform admin -----

    public function test_a_platform_admin_handles_every_campaign_and_creates_platform_campaigns(): void
    {
        $company = $this->ownCampaign('company-one');

        $platform = $this->api('POST', '/admin/campaigns', $this->body('job-fair-pkp', ['type' => 'job_fair']), $this->platformToken)->assertCreated()->assertJsonPath('data.company', null)->json('data.id');
        $forCompany = $this->api('POST', '/admin/campaigns', $this->body('for-company', ['company_id' => $this->otherCompanyId]), $this->platformToken)->assertCreated()->assertJsonPath('data.company.id', $this->otherCompanyId)->json('data.id');
        $this->api('POST', '/admin/campaigns', $this->body('ghost', ['company_id' => 999999]), $this->platformToken)->assertNotFound();

        $row = DB::table('campaign_campaigns')->where('id', $platform)->first();
        $this->assertNull($row->company_id);
        $this->assertNull($row->created_by_user_id);
        $this->assertNotNull($row->created_by_admin_id);

        $this->assertCount(3, $this->api('GET', '/admin/campaigns', [], $this->platformToken)->assertOk()->json('data'));
        $this->assertSame([$forCompany], array_column($this->api('GET', '/admin/campaigns?filter[company_id]='.$this->otherCompanyId, [], $this->platformToken)->json('data'), 'id'));
        $this->api('GET', "/admin/campaigns/{$company}", [], $this->platformToken)->assertOk()->assertJsonPath('data.code', 'company-one');
        $this->api('PUT', "/admin/campaigns/{$platform}", ['name' => 'Changed', 'type' => 'job_fair', 'is_active' => true], $this->platformToken)->assertOk()->assertJsonPath('data.name', 'Changed');
        $this->api('GET', "/admin/campaigns/{$platform}/results", [], $this->platformToken)->assertOk()->assertJsonPath('data.registrations', 0);
        $this->api('DELETE', "/admin/campaigns/{$platform}", [], $this->platformToken)->assertNoContent();
    }

    public function test_the_admin_routes_need_the_permission_and_a_company_cannot_see_platform_campaigns(): void
    {
        $platform = $this->campaignRowId('platform-one');

        $this->api('GET', '/admin/campaigns', [], $this->adminToken([], 'noperm@example.test'))->assertStatus(403);
        $this->api('GET', '/admin/campaigns', [], $this->seekerToken)->assertStatus(401);
        $this->api('GET', "/company/campaigns/{$platform}", [], $this->adminToken)->assertNotFound();
    }

    // ----- public page and registration -----

    public function test_the_public_page_shows_only_a_running_campaign(): void
    {
        $this->ownCampaign('running');
        $this->ownCampaign('off', ['is_active' => false]);
        $this->ownCampaign('over', ['ends_on' => '2020-01-01']);
        $this->ownCampaign('soon', ['starts_on' => '2099-01-01']);
        $this->campaignRowId('platform-run');

        $this->getJson('/api/v1/campaigns/RUNNING')->assertOk()
            ->assertJsonPath('data.code', 'running')
            ->assertJsonPath('data.type', 'job_fair')
            ->assertJsonPath('data.company.id', $this->companyId)
            ->assertJsonMissingPath('data.id');
        $this->getJson('/api/v1/campaigns/platform-run')->assertOk()->assertJsonPath('data.company', null);

        foreach (['off', 'over', 'soon', 'nothing'] as $code) {
            $this->getJson("/api/v1/campaigns/{$code}")->assertNotFound();
        }
    }

    public function test_a_registration_keeps_the_code_of_a_running_campaign_only(): void
    {
        $this->ownCampaign('running');
        $this->ownCampaign('off', ['is_active' => false]);

        $register = fn (string $email, string $code): ?string => UserModel::query()->findOrFail(
            $this->postJson('/api/v1/auth/register', ['name' => 'Siti', 'email' => $email, 'password' => 'a-long-enough-password', 'campaign_code' => $code])->assertCreated()->json('data.user.id')
        )->registration_campaign_code;

        $this->assertSame('running', $register('a@example.test', 'RUNNING'));
        $this->assertNull($register('b@example.test', 'off'));
        $this->assertNull($register('c@example.test', 'unknown'));
    }
}
