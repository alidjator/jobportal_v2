<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Platform\Application\PlatformSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/**
 * `docs/design/career-modes.md` sections 11 and 12: the portal of the career site (marketplace or a company site), its identity set by a platform admin and its brand set by a
 * company or group admin, both as a draft that is previewed and then published; a company the site cannot show is skipped and the platform is warned.
 */
final class PortalTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private int $group;

    private int $holding;

    private int $sibling;

    private int $outsider;

    private int $holdingAdmin;

    private int $recruiter;

    private ?string $adminToken = null;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-25 09:00:00');

        $this->holding = $this->companyRowId('pt-holding');
        $this->sibling = $this->companyRowId('pt-saudara');
        $this->outsider = $this->companyRowId('pt-luar');
        $this->holdingAdmin = $this->member($this->holding, 'hana@example.test', 'company_admin');
        $this->recruiter = $this->member($this->holding, 'rani@example.test', 'recruiter');

        $created = $this->admin('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding Nusantara', 'legal_form' => 'pt']])->assertCreated();
        $this->group = (int) $created->json('data.id');
        $this->admin('PUT', "groups/{$this->group}/legal-entities/{$created->json('data.legal_entities.0.id')}/tenant", ['company_id' => $this->holding])->assertOk();
        $this->admin('PUT', "groups/{$this->group}/members/{$this->holdingAdmin}", ['permissions' => ['group.settings.manage', 'group.structure.manage']])->assertOk();
        $second = $this->asMember($this->holdingAdmin, 'POST', 'organization/legal-entities', ['legal_name' => 'PT Saudara Nusantara', 'legal_form' => 'pt'])->assertCreated();
        $this->admin('PUT', "groups/{$this->group}/legal-entities/{$second->json('data.id')}/tenant", ['company_id' => $this->sibling])->assertOk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function member(int $companyId, string $email, string $role): int
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $email, 'email' => $email, 'password' => Hash::make('password-123'), 'status' => 'active', 'locale' => 'en',
            'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return (int) $user->id;
    }

    /** @param  array<string, mixed>  $body */
    private function admin(string $method, string $uri, array $body = [], array $permissions = ['companies.manage', 'portal.manage']): TestResponse
    {
        if ($this->adminToken === null) {
            $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
            Artisan::call('identity:sync-permissions');
            $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'admin-'.md5(implode(',', $permissions)).'@example.test', 'password' => 'hash', 'status' => 'active']);
            $admin->givePermissionTo($permissions);
            $this->adminToken = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
        }

        $this->app['auth']->forgetGuards();

        return $this->withToken($this->adminToken)->json($method, "/api/v1/admin/{$uri}", $body);
    }

    /** @param  array<string, mixed>  $body */
    private function asMember(int $userId, string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $token = UserModel::query()->findOrFail($userId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;

        return $this->withToken($token)->json($method, "/api/v1/{$uri}", $body);
    }

    /** @param  array<string, string>  $headers */
    private function portal(array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        // The visitor is anonymous: the token of the last call must not stay on the client (it would also set the language from the account).
        return $this->flushHeaders()->withHeaders($headers)->getJson('/api/v1/portal');
    }

    /** @param  list<int>  $companies */
    private function publishIdentity(string $scope, array $companies): void
    {
        $this->admin('PUT', 'portal/draft', ['mode' => 'company', 'scope' => $scope, 'company_ids' => $companies])->assertOk();
        $this->admin('POST', 'portal/draft/publish')->assertOk();
    }

    /** @return array<string, mixed> */
    private function brand(array $changes = []): array
    {
        return [
            'name' => 'Karier Nusantara', 'tagline_id' => 'Bergabunglah bersama kami', 'tagline_en' => 'Join us', 'welcome_id' => 'Selamat datang', 'welcome_en' => 'Welcome',
            'preset' => 4, 'theme_mode' => 'dark', 'contrast' => true, 'width' => 'full', 'header_color' => true, ...$changes,
        ];
    }

    public function test_without_a_published_identity_the_portal_is_the_marketplace(): void
    {
        $this->portal()->assertOk()
            ->assertJsonPath('data.mode', 'marketplace')->assertJsonPath('data.scope', null)->assertJsonPath('data.companies', [])->assertJsonPath('data.preview', false);

        $this->admin('GET', 'portal')->assertOk()->assertJsonPath('data.live_is_default', true)->assertJsonPath('data.live.mode', 'marketplace')->assertJsonPath('data.draft', null);
    }

    public function test_the_public_portal_is_cached_for_a_minute_and_revalidated_with_its_etag(): void
    {
        $first = $this->portal()->assertOk();

        $this->assertStringContainsString('max-age=60', (string) $first->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $first->headers->get('Cache-Control'));
        $this->portal(['If-None-Match' => (string) $first->headers->get('ETag')])->assertStatus(304);
    }

    public function test_the_identity_needs_the_portal_permission(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/admin/portal')->assertUnauthorized();

        $this->adminToken = null;
        $this->admin('GET', 'portal', [], ['companies.manage'])->assertForbidden();
    }

    public function test_a_draft_is_not_live_until_it_is_published_and_publishing_audits_the_change(): void
    {
        $this->admin('PUT', 'portal/draft', ['mode' => 'company', 'scope' => 'group', 'company_ids' => [$this->holding, $this->sibling]])->assertOk()
            ->assertJsonPath('data.draft.company_ids', [$this->holding, $this->sibling])->assertJsonPath('data.draft_differs', true)->assertJsonPath('data.live.mode', 'marketplace');
        $this->portal()->assertJsonPath('data.mode', 'marketplace');

        $this->admin('POST', 'portal/draft/publish')->assertOk()->assertJsonPath('data.draft', null)->assertJsonPath('data.live.scope', 'group')->assertJsonPath('data.live_is_default', false);

        $this->portal()->assertOk()->assertJsonPath('data.mode', 'company')->assertJsonPath('data.scope', 'group')->assertJsonPath('data.name', 'Grup Nusantara')
            ->assertJsonPath('data.preset', 1)->assertJsonPath('data.tagline', null);
        $this->assertSame(['pt-holding', 'pt-saudara'], array_column($this->portal()->json('data.companies'), 'slug'));

        $entry = DB::table('platform_audit_logs')->where('action', 'portal.identity_published')->first();
        $metadata = json_decode((string) $entry->metadata, true);
        $this->assertSame('marketplace', $metadata['before']['mode']);
        $this->assertSame([$this->holding, $this->sibling], $metadata['after']['company_ids']);

        $this->admin('PUT', 'portal/draft', ['mode' => 'company', 'scope' => 'group', 'company_ids' => [$this->holding, $this->sibling]])->assertOk()->assertJsonPath('data.draft_differs', false);
        $this->admin('POST', 'portal/draft/publish')->assertOk();
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'portal.identity_published')->count(), 'an unchanged identity audits nothing');
    }

    public function test_publishing_checks_the_scope_and_the_companies(): void
    {
        $rejects = function (array $draft, string $code) {
            $this->admin('PUT', 'portal/draft', $draft)->assertOk();
            $this->admin('POST', 'portal/draft/publish')->assertStatus(422)->assertJsonPath('type', "urn:onesis:error:{$code}");
        };

        $rejects(['mode' => 'marketplace', 'scope' => null, 'company_ids' => [$this->holding]], 'portal-marketplace-takes-no-companies');
        $rejects(['mode' => 'company', 'scope' => null, 'company_ids' => [$this->holding]], 'portal-scope-required');
        $rejects(['mode' => 'company', 'scope' => 'company', 'company_ids' => [$this->holding, $this->sibling]], 'portal-company-needs-one-company');
        $rejects(['mode' => 'company', 'scope' => 'group', 'company_ids' => []], 'portal-group-needs-companies');
        $rejects(['mode' => 'company', 'scope' => 'group', 'company_ids' => [$this->holding, $this->outsider]], 'portal-companies-not-in-one-group');
        $rejects(['mode' => 'company', 'scope' => 'group', 'company_ids' => [$this->outsider]], 'portal-companies-not-in-one-group');
        $rejects(['mode' => 'company', 'scope' => 'group', 'company_ids' => [$this->holding, 987654]], 'portal-company-not-live');

        DB::table('company_companies')->where('id', $this->sibling)->update(['status' => 'suspended']);
        $rejects(['mode' => 'company', 'scope' => 'group', 'company_ids' => [$this->holding, $this->sibling]], 'portal-company-not-live');

        $this->assertSame(0, DB::table('platform_portal_identity')->count(), 'nothing went live');
        $this->admin('GET', 'portal')->assertJsonPath('data.draft.mode', 'company');
    }

    public function test_a_company_without_a_group_has_a_site_of_scope_company(): void
    {
        $this->publishIdentity('company', [$this->outsider]);

        $this->portal()->assertOk()->assertJsonPath('data.scope', 'company')->assertJsonPath('data.name', 'PT-LUAR')->assertJsonPath('data.companies.0.slug', 'pt-luar');
    }

    public function test_publishing_without_a_draft_and_the_shape_of_a_draft_are_refused(): void
    {
        $this->admin('POST', 'portal/draft/publish')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:portal-draft-missing');
        $this->admin('POST', 'portal/draft/preview')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:portal-draft-missing');
        $this->admin('PUT', 'portal/draft', ['mode' => 'enterprise', 'company_ids' => []])->assertStatus(422);
        $this->admin('PUT', 'portal/draft', ['mode' => 'company', 'scope' => 'planet', 'company_ids' => []])->assertStatus(422);
        $this->admin('PUT', 'portal/draft', ['mode' => 'company', 'scope' => 'group'])->assertStatus(422);
    }

    public function test_a_draft_can_be_discarded_and_the_live_identity_stays(): void
    {
        $this->publishIdentity('company', [$this->holding]);
        $this->admin('PUT', 'portal/draft', ['mode' => 'marketplace', 'scope' => null, 'company_ids' => []])->assertOk();

        $this->admin('DELETE', 'portal/draft')->assertOk()->assertJsonPath('data.draft', null)->assertJsonPath('data.live.mode', 'company');
        $this->portal()->assertJsonPath('data.mode', 'company');
    }

    public function test_a_preview_shows_the_draft_to_whoever_holds_the_token_and_is_never_cached(): void
    {
        $this->publishIdentity('company', [$this->holding]);
        $this->admin('PUT', 'portal/draft', ['mode' => 'company', 'scope' => 'group', 'company_ids' => [$this->holding, $this->sibling]])->assertOk();

        $preview = $this->admin('POST', 'portal/draft/preview')->assertOk()->assertJsonPath('data.expires_at', Carbon::now()->addHours(2)->toIso8601String());
        $token = (string) $preview->json('data.token');

        $this->portal()->assertJsonPath('data.scope', 'company')->assertJsonPath('data.preview', false);

        $seen = $this->portal(['X-Portal-Preview' => $token])->assertOk()->assertJsonPath('data.scope', 'group')->assertJsonPath('data.preview', true);
        $this->assertCount(2, $seen->json('data.companies'));
        $this->assertStringContainsString('no-store', (string) $seen->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $seen->headers->get('Cache-Control'));

        $this->portal(['X-Portal-Preview' => 'not-a-token'])->assertOk()->assertJsonPath('data.scope', 'company')->assertJsonPath('data.preview', false);

        Carbon::setTestNow('2026-10-25 11:00:01');
        $this->portal(['X-Portal-Preview' => $token])->assertOk()->assertJsonPath('data.scope', 'company')->assertJsonPath('data.preview', false);
    }

    public function test_a_group_admin_keeps_the_brand_of_the_group_as_a_draft_and_publishes_it(): void
    {
        $this->publishIdentity('group', [$this->holding, $this->sibling]);

        $page = $this->asMember($this->holdingAdmin, 'GET', 'organization/portal')->assertOk();
        $page->assertJsonPath('data.default_name', 'Grup Nusantara')->assertJsonPath('data.live_is_default', true)->assertJsonPath('data.draft', null)->assertJsonPath('data.live.preset', 1);

        $this->asMember($this->holdingAdmin, 'PUT', 'organization/portal/draft', $this->brand())->assertOk()
            ->assertJsonPath('data.draft.name', 'Karier Nusantara')->assertJsonPath('data.draft_differs', true)->assertJsonPath('data.live_is_default', true);
        $this->portal()->assertJsonPath('data.name', 'Grup Nusantara');

        $token = $this->asMember($this->holdingAdmin, 'POST', 'organization/portal/draft/preview')->assertOk()->json('data.token');
        $this->portal(['X-Portal-Preview' => $token])->assertJsonPath('data.name', 'Karier Nusantara')->assertJsonPath('data.preset', 4)->assertJsonPath('data.preview', true);

        $this->asMember($this->holdingAdmin, 'POST', 'organization/portal/draft/publish')->assertOk()->assertJsonPath('data.draft', null)->assertJsonPath('data.live.name', 'Karier Nusantara')->assertJsonPath('data.live_is_default', false);

        $this->portal(['Accept-Language' => 'en'])->assertJsonPath('data.name', 'Karier Nusantara')->assertJsonPath('data.tagline', 'Join us')->assertJsonPath('data.welcome', 'Welcome')->assertJsonPath('data.preset', 4);
        $this->portal(['Accept-Language' => 'id'])->assertJsonPath('data.tagline', 'Bergabunglah bersama kami')->assertJsonPath('data.welcome', 'Selamat datang')
            ->assertJsonPath('data.theme_mode', 'dark')->assertJsonPath('data.contrast', true)->assertJsonPath('data.width', 'full')->assertJsonPath('data.header_color', true);

        $entry = DB::table('platform_audit_logs')->where('action', 'portal.brand_published')->first();
        $this->assertSame($this->group, (int) $entry->group_id);
        $this->assertSame($this->holdingAdmin, (int) $entry->actor_id);
        $metadata = json_decode((string) $entry->metadata, true);
        $this->assertNull($metadata['before']['name']);
        $this->assertSame('Karier Nusantara', $metadata['after']['name']);
    }

    public function test_the_brand_of_a_group_needs_group_settings_manage_and_a_valid_brand(): void
    {
        $this->asMember($this->recruiter, 'GET', 'organization/portal')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-group-member');

        $stranger = $this->member($this->outsider, 'luar@example.test', 'company_admin');
        $this->asMember($stranger, 'PUT', 'organization/portal/draft', $this->brand())->assertForbidden();

        $this->asMember($this->holdingAdmin, 'POST', 'organization/portal/draft/publish')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:portal-draft-missing');
        $this->asMember($this->holdingAdmin, 'PUT', 'organization/portal/draft', $this->brand(['preset' => 19]))->assertStatus(422);
        $this->asMember($this->holdingAdmin, 'PUT', 'organization/portal/draft', $this->brand(['name' => str_repeat('a', 121)]))->assertStatus(422);
        $this->asMember($this->holdingAdmin, 'PUT', 'organization/portal/draft', $this->brand(['theme_mode' => 'sepia']))->assertStatus(422);
        $this->asMember($this->holdingAdmin, 'PUT', 'organization/portal/draft', $this->brand(['width' => 'wide']))->assertStatus(422);
        $this->asMember($this->holdingAdmin, 'PUT', 'organization/portal/draft', $this->brand(['contrast' => 'maybe']))->assertStatus(422);
        $this->asMember($this->holdingAdmin, 'PUT', 'organization/portal/draft', $this->brand(['welcome_id' => str_repeat('a', 501)]))->assertStatus(422);
    }

    public function test_a_company_admin_keeps_the_brand_of_a_site_of_one_company(): void
    {
        $this->publishIdentity('company', [$this->holding]);

        $this->asMember($this->holdingAdmin, 'GET', 'company/portal')->assertOk()->assertJsonPath('data.default_name', 'PT-HOLDING')->assertJsonPath('data.live_is_default', true);
        $this->asMember($this->holdingAdmin, 'PUT', 'company/portal/draft', $this->brand(['name' => '', 'preset' => 7]))->assertOk()->assertJsonPath('data.draft.name', null)->assertJsonPath('data.draft.theme_mode', 'dark');
        $this->asMember($this->holdingAdmin, 'POST', 'company/portal/draft/publish')->assertOk();

        $this->portal(['Accept-Language' => 'id'])->assertOk()->assertJsonPath('data.name', 'PT-HOLDING')->assertJsonPath('data.preset', 7)->assertJsonPath('data.tagline', 'Bergabunglah bersama kami');

        $entry = DB::table('platform_audit_logs')->where('action', 'portal.brand_published')->first();
        $this->assertSame($this->holding, (int) $entry->company_id);

        $this->asMember($this->holdingAdmin, 'PUT', 'company/portal/draft', $this->brand())->assertOk();
        $this->asMember($this->holdingAdmin, 'DELETE', 'company/portal/draft')->assertOk()->assertJsonPath('data.draft', null)->assertJsonPath('data.live.preset', 7);
    }

    public function test_a_brand_without_appearance_settings_gets_the_defaults(): void
    {
        $this->publishIdentity('company', [$this->holding]);

        $this->asMember($this->holdingAdmin, 'PUT', 'company/portal/draft', ['preset' => 2, 'name' => 'Karier'])->assertOk()
            ->assertJsonPath('data.draft.theme_mode', 'light')->assertJsonPath('data.draft.contrast', false)->assertJsonPath('data.draft.width', 'container')->assertJsonPath('data.draft.header_color', false);
        $this->asMember($this->holdingAdmin, 'POST', 'company/portal/draft/publish')->assertOk();

        $this->portal()->assertJsonPath('data.theme_mode', 'light')->assertJsonPath('data.contrast', false)->assertJsonPath('data.width', 'container')->assertJsonPath('data.header_color', false);
    }

    public function test_the_brand_of_a_company_needs_the_company_profile_permission(): void
    {
        $this->asMember($this->recruiter, 'GET', 'company/portal')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->asMember($this->recruiter, 'PUT', 'company/portal/draft', $this->brand())->assertForbidden();
    }

    public function test_a_company_the_site_cannot_show_is_skipped_and_the_site_stays_up(): void
    {
        $this->publishIdentity('group', [$this->holding, $this->sibling]);

        DB::table('company_companies')->where('id', $this->sibling)->update(['status' => 'suspended']);
        $this->assertSame(['pt-holding'], array_column($this->portal()->assertOk()->assertJsonPath('data.mode', 'company')->json('data.companies'), 'slug'));
        $this->admin('GET', 'portal')->assertJsonPath('data.skipped.0.company_id', $this->sibling)->assertJsonPath('data.skipped.0.reason', 'suspended');

        DB::table('company_companies')->where('id', $this->sibling)->update(['status' => 'active', 'is_visible' => false]);
        $this->admin('GET', 'portal')->assertJsonPath('data.skipped.0.reason', 'hidden');

        DB::table('platform_portal_identity')->update(['company_ids' => json_encode([$this->holding, 987654])]);
        $this->admin('GET', 'portal')->assertJsonPath('data.skipped.0.reason', 'missing')->assertJsonPath('data.skipped.0.name', null);

        DB::table('company_companies')->where('id', $this->holding)->update(['is_visible' => false]);
        $this->portal()->assertOk()->assertJsonPath('data.mode', 'company')->assertJsonPath('data.companies', [])->assertJsonPath('data.name', 'Grup Nusantara');
    }

    /** The messages to the operations address, oldest first. @return list<\Symfony\Component\Mime\Email> */
    private function opsMessages(): array
    {
        $messages = array_map(
            fn ($message) => $message->getOriginalMessage(),
            $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all(),
        );

        return array_values(array_filter($messages, fn ($message): bool => $message->getTo()[0]->getAddress() === 'ops@example.test'));
    }

    /** @return list<string> */
    private function sentTo(): array
    {
        return array_map(fn ($message): string => $message->getTo()[0]->getAddress(), $this->opsMessages());
    }

    public function test_the_daily_check_warns_once_per_change_and_once_when_it_recovers(): void
    {
        $this->artisan('portal:check')->expectsOutputToContain('Skipped companies: 0, email sent: no')->assertSuccessful();

        $this->app->make(SettingsStore::class)->setGlobal(PlatformSettings::OPERATIONS_EMAIL, 'ops@example.test', null);
        $this->publishIdentity('group', [$this->holding, $this->sibling]);
        $this->artisan('portal:check')->expectsOutputToContain('email sent: no')->assertSuccessful();
        $this->assertSame([], $this->sentTo());

        DB::table('company_companies')->where('id', $this->sibling)->update(['status' => 'suspended']);
        $this->artisan('portal:check')->expectsOutputToContain('Skipped companies: 1, email sent: yes')->assertSuccessful();
        $this->assertSame(['ops@example.test'], $this->sentTo());
        $this->assertStringContainsString('PT-SAUDARA (suspended)', (string) $this->opsMessages()[0]->getTextBody());

        $this->artisan('portal:check')->expectsOutputToContain('Skipped companies: 1, email sent: no')->assertSuccessful();
        $this->assertCount(1, $this->sentTo());

        DB::table('company_companies')->where('id', $this->sibling)->update(['status' => 'active']);
        $this->artisan('portal:check')->expectsOutputToContain('Skipped companies: 0, email sent: yes')->assertSuccessful();
        $this->assertCount(2, $this->sentTo());
        $this->assertNull(DB::table('platform_portal_identity')->value('alert_signature'));

        $this->artisan('portal:check')->expectsOutputToContain('email sent: no')->assertSuccessful();
        $this->assertCount(2, $this->sentTo());
    }

    public function test_the_check_sends_nothing_and_remembers_nothing_without_an_operations_address(): void
    {
        $this->publishIdentity('group', [$this->holding, $this->sibling]);
        DB::table('company_companies')->where('id', $this->sibling)->update(['status' => 'suspended']);

        $this->artisan('portal:check')->expectsOutputToContain('email sent: no')->assertSuccessful();

        $this->assertSame([], $this->sentTo());
        $this->assertNull(DB::table('platform_portal_identity')->value('alert_signature'));

        $this->app->make(SettingsStore::class)->setGlobal(PlatformSettings::OPERATIONS_EMAIL, 'ops@example.test', null);
        $this->artisan('portal:check')->expectsOutputToContain('email sent: yes')->assertSuccessful();
    }
}
