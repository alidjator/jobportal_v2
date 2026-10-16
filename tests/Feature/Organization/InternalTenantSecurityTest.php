<?php

namespace Tests\Feature\Organization;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Mail\AccountNoticeMail;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/erp-sso.md` sections 3 to 5: two-factor through the group setting, 12-hour tokens and the revocations when a tenant becomes internal. */
final class InternalTenantSecurityTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string PASSWORD = 'password-123';

    private int $holding;

    private int $external;

    private UserModel $groupAdmin;

    private UserModel $recruiter;

    private UserModel $outsider;

    private int $group;

    private int $entity;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        Mail::fake();

        $this->holding = $this->companyRowId('pt-holding');
        $this->external = $this->companyRowId('pt-luar');
        $this->groupAdmin = $this->member($this->holding, 'andi@example.test', 'company_admin');
        $this->recruiter = $this->member($this->holding, 'rina@example.test', 'recruiter');
        $this->outsider = $this->member($this->external, 'eko@example.test', 'recruiter');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function member(int $companyId, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make(self::PASSWORD), 'status' => 'active', 'locale' => 'en',
            'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return $user;
    }

    private function token(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    private function tokenCount(UserModel $user): int
    {
        return DB::table('personal_access_tokens')->where('tokenable_type', 'user')->where('tokenable_id', $user->id)->count();
    }

    /** @param  array<string, mixed>  $body */
    private function admin(string $method, string $uri, array $body = []): TestResponse
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->firstOrCreate(['email' => 'ops@example.test'], ['name' => 'Ops', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['companies.manage']);
        $this->app['auth']->forgetGuards();

        return $this->withToken($admin->createToken('console', ['*'], now()->addHour())->plainTextToken)->json($method, "/api/v1/admin/{$uri}", $body);
    }

    /** @param  array<string, mixed>  $body */
    private function asMember(UserModel $user, string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token($user))->json($method, "/api/v1{$uri}", $body);
    }

    private function login(string $email): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
    }

    private function code(string $secret, int $userId): string
    {
        DB::table('identity_user_two_factor')->where('user_id', $userId)->update(['last_step' => null]);
        $google2fa = new Google2FA;

        return $google2fa->oathTotp($secret, $google2fa->getTimestamp());
    }

    private function linkTheHolding(): void
    {
        $created = $this->admin('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding', 'legal_form' => 'pt']])->assertCreated();
        $this->group = (int) $created->json('data.id');
        $this->entity = (int) $created->json('data.legal_entities.0.id');
        $this->admin('PUT', "groups/{$this->group}/legal-entities/{$this->entity}/tenant", ['company_id' => $this->holding])->assertOk();
        $this->admin('PUT', "groups/{$this->group}/members/{$this->groupAdmin->id}", ['permissions' => ['group.settings.manage']])->assertOk();
    }

    private function groupRequiresTwoFactor(bool $on): void
    {
        $this->asMember($this->groupAdmin, 'PATCH', '/organization/settings', ['group.require_two_factor' => $on])->assertOk()->assertJsonPath('data.0.value', $on);
    }

    // ----- a tenant becomes internal -----

    public function test_linking_a_tenant_ends_every_session_of_its_members_and_tells_them(): void
    {
        $this->token($this->groupAdmin);
        $this->token($this->recruiter);
        $this->token($this->outsider);

        $this->linkTheHolding();

        $this->assertSame(0, $this->tokenCount($this->groupAdmin));
        $this->assertSame(0, $this->tokenCount($this->recruiter));
        $this->assertSame(1, $this->tokenCount($this->outsider), 'an external tenant is untouched');
        Mail::assertQueued(AccountNoticeMail::class, 2);
    }

    // ----- two-factor through the group setting -----

    public function test_a_member_of_an_internal_tenant_needs_a_second_factor_while_the_group_requires_it(): void
    {
        $this->linkTheHolding();

        $this->login('rina@example.test')->assertOk()->assertJsonPath('data.status', 'two_factor_enrolment_required')->assertJsonMissingPath('data.token');
        $this->login('eko@example.test')->assertOk()->assertJsonPath('data.token_type', 'Bearer');

        $this->groupRequiresTwoFactor(false);
        $this->login('rina@example.test')->assertOk()->assertJsonPath('data.token_type', 'Bearer');
        $this->login('andi@example.test')->assertOk()->assertJsonPath('data.status', 'two_factor_enrolment_required');
    }

    public function test_switching_the_group_setting_on_ends_the_sessions_of_members_without_a_factor(): void
    {
        $this->linkTheHolding();
        $this->groupRequiresTwoFactor(false);

        $enrolled = $this->member($this->holding, 'rani@example.test', 'recruiter');
        $secret = (string) $this->asMember($enrolled, 'POST', '/me/two-factor')->assertOk()->json('data.secret');
        $this->asMember($enrolled, 'POST', '/me/two-factor/confirm', ['code' => $this->code($secret, $enrolled->id)])->assertOk();
        $this->token($this->recruiter);
        $this->token($enrolled);
        $keptBefore = $this->tokenCount($enrolled);
        Mail::fake();

        $this->groupRequiresTwoFactor(true);

        $this->assertSame(0, $this->tokenCount($this->recruiter), 'no factor yet: the session ends');
        $this->assertSame($keptBefore, $this->tokenCount($enrolled), 'a member who already has a factor keeps the session');
        $this->assertGreaterThan(0, $this->tokenCount($this->groupAdmin), 'a company admin was required already');
        Mail::assertQueued(AccountNoticeMail::class, 1);
    }

    // ----- token lifetime -----

    public function test_tokens_of_internal_members_live_twelve_hours_and_others_thirty_days(): void
    {
        $this->linkTheHolding();
        $this->groupRequiresTwoFactor(false);

        $this->login('rina@example.test')->assertOk()->assertJsonPath('data.expires_at', '2026-10-05T21:00:00+00:00');
        $this->login('eko@example.test')->assertOk()->assertJsonPath('data.expires_at', '2026-11-04T09:00:00+00:00');
    }

    public function test_the_second_step_of_the_sign_in_issues_the_twelve_hour_token(): void
    {
        $this->linkTheHolding();

        $challenge = $this->login('rina@example.test')->assertOk()->assertJsonPath('data.status', 'two_factor_enrolment_required');
        $this->app['auth']->forgetGuards();
        $done = $this->flushHeaders()->postJson('/api/v1/auth/two-factor', [
            'challenge_token' => $challenge->json('data.challenge_token'), 'code' => $this->code($challenge->json('data.enrolment.secret'), $this->recruiter->id),
        ])->assertOk();

        $done->assertJsonPath('data.expires_at', '2026-10-05T21:00:00+00:00');
    }

    public function test_unlinking_the_tenant_changes_nothing_for_existing_tokens_and_new_ones_follow_the_external_rules(): void
    {
        $this->linkTheHolding();
        $this->groupRequiresTwoFactor(false);
        $this->token($this->recruiter);

        $this->admin('DELETE', "groups/{$this->group}/legal-entities/{$this->entity}/tenant")->assertStatus(409);
        $this->admin('DELETE', "groups/{$this->group}/members/{$this->groupAdmin->id}")->assertNoContent();
        $this->admin('DELETE', "groups/{$this->group}/legal-entities/{$this->entity}/tenant")->assertNoContent();

        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $this->recruiter->id)->where('name', 'phone')->count());
        $this->login('rina@example.test')->assertOk()->assertJsonPath('data.expires_at', '2026-11-04T09:00:00+00:00');
    }
}
