<?php

namespace Tests\Feature\Company;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

final class CompanyOnboardingTest extends FeatureTestCase
{
    private const string LINK = 'https://careers.test/invitations/{token}';

    private int $industryId;

    private int $organizationTypeId;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        config(['app.invitation_url' => self::LINK]);

        $this->industryId = $this->master('reference_industries', 'technology');
        $this->organizationTypeId = $this->master('reference_organization_types', 'private-company');

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->adminToken = $this->adminToken(['companies.manage']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function master(string $table, string $slug, bool $active = true): int
    {
        return (int) DB::table($table)->insertGetId([
            'name' => json_encode(['id' => $slug, 'en' => $slug]), 'slug' => $slug, 'sort_order' => 0, 'is_active' => $active,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email = 'ops@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    /** @param  array<string, mixed>  $overrides */
    private function createCompany(array $overrides = [], ?string $token = null, string $language = 'en'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->withHeader('Accept-Language', $language)->postJson('/api/v1/admin/companies', [
            'name' => 'PT Maju Bersama',
            'industry_id' => $this->industryId,
            'organization_type_id' => $this->organizationTypeId,
            'admin_email' => 'Owner@Example.test',
            ...$overrides,
        ]);
    }

    /** The plain token that the invitation email carried, read from the message the mail transport received. */
    private function tokenFromMail(int $index = 0): string
    {
        $messages = $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertArrayHasKey($index, $messages->all());

        preg_match('#careers\.test/invitations/([A-Za-z0-9_-]{43})#', (string) $messages[$index]->getOriginalMessage()->getTextBody(), $matches);
        $this->assertNotEmpty($matches, 'The invitation email holds no invitation link.');

        return $matches[1];
    }

    private function accept(string $token, array $body = ['name' => 'Budi Santoso', 'password' => 'password-123']): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson("/api/v1/auth/invitations/{$token}/accept", $body);
    }

    private function preview(string $token): TestResponse
    {
        return $this->getJson("/api/v1/auth/invitations/{$token}");
    }

    private function companyMe(string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/company/me');
    }

    public function test_creating_a_company_needs_an_admin_with_the_permission(): void
    {
        $this->postJson('/api/v1/admin/companies', [])->assertStatus(401);
        $this->createCompany(token: $this->adminToken(['faqs.manage'], 'other@example.test'))->assertStatus(403);
        $this->assertSame(0, DB::table('company_companies')->count());
    }

    public function test_an_admin_creates_a_company_and_invites_its_first_company_admin(): void
    {
        $response = $this->createCompany()->assertCreated();

        $response->assertJsonPath('data.company.name', 'PT Maju Bersama')
            ->assertJsonPath('data.company.slug', 'pt-maju-bersama')
            ->assertJsonPath('data.company.status', 'active')
            ->assertJsonPath('data.company.industry_id', $this->industryId)
            ->assertJsonPath('data.invitation.email', 'owner@example.test')
            ->assertJsonPath('data.invitation.role', 'company_admin')
            ->assertJsonPath('data.invitation.sent_at', '2026-10-05T09:00:00+00:00')
            ->assertJsonPath('data.invitation.expires_at', '2026-10-12T09:00:00+00:00');
        $this->assertArrayNotHasKey('token', $response->json('data.invitation'));

        $company = DB::table('company_companies')->first();
        $this->assertSame('PT Maju Bersama', $company->name);
        $this->assertSame($this->organizationTypeId, (int) $company->organization_type_id);
        $this->assertNotNull($company->created_by_admin_id);

        $invitation = DB::table('company_invitations')->first();
        $this->assertSame($company->id, $invitation->company_id);
        $this->assertSame('owner@example.test', $invitation->email);
        $this->assertSame(hash('sha256', $this->tokenFromMail()), $invitation->token_hash);
        $this->assertNull($invitation->invited_by_user_id);
        $this->assertNotNull($invitation->invited_by_admin_id);
        $this->assertNull($invitation->accepted_at);
    }

    public function test_the_invitation_email_names_the_company_and_the_role_in_the_request_language(): void
    {
        $this->createCompany(language: 'id')->assertCreated();

        $message = $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()[0]->getOriginalMessage();

        $this->assertSame('owner@example.test', $message->getTo()[0]->getAddress());
        $this->assertSame('Undangan bergabung dengan PT Maju Bersama', $message->getSubject());
        $this->assertStringContainsString('sebagai Admin perusahaan', (string) $message->getTextBody());
        $this->assertStringContainsString('berlaku selama 7 hari', (string) $message->getTextBody());
    }

    public function test_a_taken_slug_gets_a_numeric_suffix(): void
    {
        $this->createCompany(['admin_email' => 'a@example.test'])->assertCreated()->assertJsonPath('data.company.slug', 'pt-maju-bersama');
        $this->createCompany(['admin_email' => 'b@example.test'])->assertCreated()->assertJsonPath('data.company.slug', 'pt-maju-bersama-2');
        $this->createCompany(['admin_email' => 'c@example.test'])->assertCreated()->assertJsonPath('data.company.slug', 'pt-maju-bersama-3');
    }

    public function test_a_long_name_keeps_the_slug_within_sixty_characters(): void
    {
        $name = str_repeat('Perusahaan ', 12);

        $first = $this->createCompany(['name' => $name, 'admin_email' => 'a@example.test'])->assertCreated()->json('data.company.slug');
        $second = $this->createCompany(['name' => $name, 'admin_email' => 'b@example.test'])->assertCreated()->json('data.company.slug');

        $this->assertLessThanOrEqual(60, strlen($first));
        $this->assertLessThanOrEqual(60, strlen($second));
        $this->assertNotSame($first, $second);
        $this->assertStringEndsWith('-2', $second);
    }

    public function test_the_request_is_validated(): void
    {
        $this->createCompany(['name' => ''])->assertStatus(422);
        $this->createCompany(['admin_email' => 'not-an-email'])->assertStatus(422);
        $this->createCompany(['industry_id' => 'x'])->assertStatus(422);
        $this->assertSame(0, DB::table('company_companies')->count());
    }

    public function test_an_unknown_or_inactive_classification_is_refused(): void
    {
        $this->createCompany(['industry_id' => 999999])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:unknown-industry');

        $inactive = $this->master('reference_organization_types', 'closed', false);
        $this->createCompany(['organization_type_id' => $inactive])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:unknown-organization-type');

        $this->assertSame(0, DB::table('company_companies')->count());
    }

    public function test_an_email_that_already_has_an_account_is_refused(): void
    {
        UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'owner@example.test', 'status' => 'active', 'locale' => 'en']);

        $this->createCompany()->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:email-already-registered');

        $this->assertSame(0, DB::table('company_companies')->count());
        $this->assertSame(0, DB::table('company_invitations')->count());
    }

    public function test_nothing_is_created_when_the_invitation_link_is_not_configured(): void
    {
        config(['app.invitation_url' => null]);

        $this->createCompany()->assertStatus(503)->assertJsonPath('type', 'urn:jobportal:error:invitation-link-unavailable');

        $this->assertSame(0, DB::table('company_companies')->count());
        $this->assertSame(0, DB::table('company_invitations')->count());
    }

    public function test_the_invitee_previews_the_invitation(): void
    {
        $this->createCompany()->assertCreated();

        $this->preview($this->tokenFromMail())->assertOk()
            ->assertJsonPath('data.company_name', 'PT Maju Bersama')
            ->assertJsonPath('data.email', 'owner@example.test')
            ->assertJsonPath('data.role', 'company_admin')
            ->assertJsonPath('data.expires_at', '2026-10-12T09:00:00+00:00');
    }

    public function test_every_kind_of_bad_token_is_the_same_not_found(): void
    {
        $this->createCompany()->assertCreated();
        $token = $this->tokenFromMail();

        $this->preview(str_repeat('a', 43))->assertStatus(404)->assertJsonPath('type', 'urn:jobportal:error:invitation-not-found');
        $this->preview('short')->assertStatus(404)->assertJsonPath('type', 'urn:jobportal:error:invitation-not-found');

        DB::table('company_invitations')->update(['revoked_at' => '2026-10-05 09:30:00']);
        $this->preview($token)->assertStatus(404)->assertJsonPath('type', 'urn:jobportal:error:invitation-not-found');
        $this->accept($token)->assertStatus(404)->assertJsonPath('type', 'urn:jobportal:error:invitation-not-found');
    }

    public function test_an_expired_invitation_is_not_found(): void
    {
        $this->createCompany()->assertCreated();
        $token = $this->tokenFromMail();

        Carbon::setTestNow('2026-10-12 08:59:59');
        $this->preview($token)->assertOk();

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->preview($token)->assertStatus(404);
        $this->accept($token)->assertStatus(404);
        $this->assertSame(0, DB::table('company_members')->count());
    }

    public function test_accepting_creates_the_account_and_membership_and_signs_the_invitee_in(): void
    {
        $this->createCompany()->assertCreated();
        $token = $this->tokenFromMail();

        $response = $this->accept($token)->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_at', '2026-11-04T09:00:00+00:00')
            ->assertJsonPath('data.user.type', 'company_member')
            ->assertJsonPath('data.user.name', 'Budi Santoso')
            ->assertJsonPath('data.user.email', 'owner@example.test')
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.status', 'active');

        $user = UserModel::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame('company_member', $user->type);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame('password-123', $user->password);
        $this->assertSame('en', $user->locale);

        $member = DB::table('company_members')->first();
        $this->assertSame($user->id, (int) $member->user_id);
        $this->assertSame('company_admin', $member->role);
        $this->assertSame('2026-10-05 09:00:00', DB::table('company_invitations')->value('accepted_at'));

        $this->companyMe($response->json('data.token'))->assertOk();
    }

    public function test_the_new_account_takes_the_language_of_the_accept_request(): void
    {
        $this->createCompany()->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->withHeader('Accept-Language', 'id')->postJson('/api/v1/auth/invitations/'.$this->tokenFromMail().'/accept', ['name' => 'Budi', 'password' => 'password-123'])
            ->assertCreated()
            ->assertJsonPath('data.user.locale', 'id');
    }

    public function test_an_invitation_can_be_accepted_only_once(): void
    {
        $this->createCompany()->assertCreated();
        $token = $this->tokenFromMail();

        $this->accept($token)->assertCreated();
        $this->accept($token, ['name' => 'Someone Else', 'password' => 'another-pass-1'])->assertStatus(404);
        $this->preview($token)->assertStatus(404);

        $this->assertSame(1, DB::table('company_members')->count());
        $this->assertSame(1, UserModel::query()->count());
    }

    public function test_accepting_needs_a_name_and_a_password_of_at_least_eight_characters(): void
    {
        $this->createCompany()->assertCreated();
        $token = $this->tokenFromMail();

        $this->accept($token, ['name' => 'Budi', 'password' => 'short'])->assertStatus(422);
        $this->accept($token, ['password' => 'password-123'])->assertStatus(422);
        $this->assertSame(0, DB::table('company_members')->count());

        $this->accept($token)->assertCreated();
    }

    public function test_accepting_fails_when_the_email_got_an_account_after_the_invitation(): void
    {
        $this->createCompany()->assertCreated();
        $token = $this->tokenFromMail();
        UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'owner@example.test', 'status' => 'active', 'locale' => 'en']);

        $this->accept($token)->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:email-already-registered');

        $this->assertSame(0, DB::table('company_members')->count());
        $this->assertNull(DB::table('company_invitations')->value('accepted_at'));
    }

    public function test_a_suspended_company_cannot_be_joined(): void
    {
        $this->createCompany()->assertCreated();
        $token = $this->tokenFromMail();
        DB::table('company_companies')->update(['status' => 'suspended']);

        $this->preview($token)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
        $this->accept($token)->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
        $this->assertSame(0, UserModel::query()->count());
    }

    public function test_the_invitation_lookups_are_limited_per_address(): void
    {
        foreach (range(1, 20) as $ignored) {
            $this->preview(str_repeat('b', 43))->assertStatus(404);
        }

        $this->preview(str_repeat('b', 43))->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_the_company_endpoint_shows_the_company_and_the_role_even_when_suspended(): void
    {
        $this->createCompany()->assertCreated();
        $session = $this->accept($this->tokenFromMail())->assertCreated();
        $token = $session->json('data.token');

        $this->companyMe($token)->assertOk()
            ->assertJsonPath('data.company.name', 'PT Maju Bersama')
            ->assertJsonPath('data.company.slug', 'pt-maju-bersama')
            ->assertJsonPath('data.company.status', 'active')
            ->assertJsonPath('data.role', 'company_admin');

        DB::table('company_companies')->update(['status' => 'suspended']);

        $this->companyMe($token)->assertOk()->assertJsonPath('data.company.status', 'suspended');
    }

    public function test_the_company_endpoint_is_for_company_members_only(): void
    {
        $this->getJson('/api/v1/company/me')->assertStatus(401);

        $seeker = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'en']);
        $this->companyMe($seeker->createToken('phone', ['*'], now()->addDay())->plainTextToken)
            ->assertStatus(403)
            ->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');

        $member = UserModel::query()->create(['type' => 'company_member', 'name' => 'Orphan', 'email' => 'orphan@example.test', 'status' => 'active', 'locale' => 'en']);
        $this->companyMe($member->createToken('phone', ['*'], now()->addDay())->plainTextToken)
            ->assertStatus(403)
            ->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');
    }

    public function test_an_account_can_belong_to_only_one_company(): void
    {
        $this->createCompany(['admin_email' => 'a@example.test'])->assertCreated();
        $this->createCompany(['name' => 'PT Lain', 'admin_email' => 'b@example.test'])->assertCreated();
        $this->accept($this->tokenFromMail(0))->assertCreated();

        $userId = (int) DB::table('company_members')->value('user_id');
        $secondCompany = (int) DB::table('company_companies')->where('slug', 'pt-lain')->value('id');

        $this->expectException(QueryException::class);
        CompanyMemberRows::insert($secondCompany, $userId);
    }

    public function test_the_creation_and_the_joining_are_audited_without_email_addresses(): void
    {
        $this->createCompany()->assertCreated();
        $this->accept($this->tokenFromMail())->assertCreated();

        $companyId = (int) DB::table('company_companies')->value('id');
        $created = DB::table('platform_audit_logs')->where('action', 'company.created')->first();
        $joined = DB::table('platform_audit_logs')->where('action', 'company.member_joined')->first();

        $this->assertSame('admin', $created->actor_type);
        $this->assertSame('company', $created->subject_type);
        $this->assertSame($companyId, (int) $created->subject_id);

        $sent = DB::table('platform_audit_logs')->where('action', 'company.invitation_sent')->first();
        $this->assertSame('admin', $sent->actor_type);
        $this->assertSame($companyId, (int) $sent->subject_id);
        $this->assertSame(['invitation_id' => (int) DB::table('company_invitations')->value('id'), 'role' => 'company_admin', 'permissions' => null], json_decode((string) $sent->metadata, true));
        $this->assertSame('user', $joined->actor_type);
        $this->assertSame($companyId, (int) $joined->subject_id);
        $this->assertSame(['user_id' => (int) $joined->actor_id, 'role' => 'company_admin'], json_decode((string) $joined->metadata, true));
        $this->assertStringNotContainsString('example.test', json_encode(DB::table('platform_audit_logs')->get()->all()));
    }

    public function test_the_company_permission_is_part_of_the_catalog_and_the_super_admin_role(): void
    {
        $this->assertTrue(DB::table('identity_permissions')->where('name', 'companies.manage')->exists());
        $this->assertTrue(
            DB::table('identity_role_permissions')
                ->join('identity_permissions', 'identity_permissions.id', '=', 'identity_role_permissions.permission_id')
                ->where('identity_permissions.name', 'companies.manage')
                ->exists(),
        );
    }
}
