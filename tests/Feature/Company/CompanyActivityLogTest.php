<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/phase-8-company-admin.md` section 2: the activity log of a company for its company admins, and its export. */
final class CompanyActivityLogTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private const string PASSWORD = 'password-123';

    private int $companyId;

    private int $otherCompanyId;

    private UserModel $owner;

    private UserModel $recruiter;

    private UserModel $stranger;

    private string $ownerToken;

    private string $recruiterToken;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');

        $this->companyId = $this->company('pt-maju-bersama');
        $this->otherCompanyId = $this->company('pt-lain');
        $this->owner = $this->member($this->companyId, 'Andi Admin', 'andi@example.test', 'company_admin');
        $this->recruiter = $this->member($this->companyId, 'Rina Rekruter', 'rina@example.test', 'recruiter');
        $this->stranger = $this->member($this->otherCompanyId, 'Lain Admin', 'lain@example.test', 'company_admin');
        $this->ownerToken = $this->tokenOf($this->owner);
        $this->recruiterToken = $this->tokenOf($this->recruiter);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function company(string $slug): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => strtoupper($slug), 'slug' => $slug, 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(int $companyId, string $name, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD),
            'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return $user;
    }

    private function tokenOf(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->ownerToken)->json($method, '/api/v1/company'.$uri, $body);
    }

    /** @param  array<string, string>  $headers */
    private function signIn(string $email, string $password = self::PASSWORD, array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password], $headers);
    }

    /** Some entries of the company, five minutes apart: a sign-in, a failed one, a team change and a platform admin's override. */
    private function seedLog(): void
    {
        $this->signIn('rina@example.test', headers: ['User-Agent' => 'PhoneApp/1.0'])->assertOk();

        Carbon::setTestNow('2026-10-05 09:05:00');
        $this->signIn('rina@example.test', 'wrong-password', ['User-Agent' => '=HYPERLINK("x")'])->assertUnauthorized();

        Carbon::setTestNow('2026-10-05 09:10:00');
        $this->api('PATCH', "/members/{$this->recruiter->id}", ['name' => 'Rina Rekruter Senior'])->assertNoContent();

        Carbon::setTestNow('2026-10-05 09:15:00');
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['settings.manage']);
        $this->app['auth']->forgetGuards();
        $this->withToken($admin->createToken('console', ['*'], now()->addHour())->plainTextToken)
            ->putJson("/api/v1/admin/companies/{$this->companyId}/settings/company.document_max_size_kb", ['value' => 1000])->assertOk();

        Carbon::setTestNow('2026-10-05 09:20:00');
    }

    /** @return list<string> */
    private function actions(TestResponse $response): array
    {
        return array_column($response->json('data'), 'action');
    }

    // ----- what the log holds -----

    public function test_an_entry_belongs_to_the_company_of_its_actor_or_of_its_subject(): void
    {
        $this->seedLog();

        $signedIn = DB::table('platform_audit_logs')->where('action', 'auth.login')->where('actor_id', $this->recruiter->id)->first();
        $failed = DB::table('platform_audit_logs')->where('action', 'auth.login_failed')->where('actor_id', $this->recruiter->id)->first();
        $platform = DB::table('platform_audit_logs')->where('action', 'settings.updated')->first();

        $this->assertSame($this->companyId, (int) $signedIn->company_id);
        $this->assertSame($this->companyId, (int) $failed->company_id, 'a failed sign-in of a member shows too');
        $this->assertSame($this->companyId, (int) $platform->company_id, 'a platform admin acting on the company');
        $this->assertSame('admin', $platform->actor_type);
    }

    public function test_an_entry_of_an_actor_with_no_company_has_none(): void
    {
        UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'password' => Hash::make(self::PASSWORD), 'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-10-01 00:00:00']);

        $this->signIn('siti@example.test')->assertOk();

        $this->assertNull(DB::table('platform_audit_logs')->where('action', 'auth.login')->where('actor_type', 'user')->orderByDesc('id')->value('company_id'));
    }

    // ----- the list -----

    public function test_a_company_admin_reads_the_log_newest_first_with_the_names_of_the_actors(): void
    {
        $this->seedLog();
        $this->signIn('lain@example.test')->assertOk();

        $response = $this->api('GET', '/activity')->assertOk();

        $this->assertSame(['settings.updated', 'company.member_renamed', 'auth.login_failed', 'auth.login'], $this->actions($response));

        $first = $response->json('data.0');
        $this->assertSame('settings.updated', $first['action']);
        $this->assertSame(['type' => 'admin', 'id' => null, 'name' => null], $first['actor'], 'the client shows "Admin platform"');
        $this->assertNull($first['ip_address'], 'the address of a platform admin stays with the platform');
        $this->assertNull($first['user_agent']);
        $this->assertSame('company', $first['subject_type']);
        $this->assertSame($this->companyId, $first['subject_id']);

        $failed = collect($response->json('data'))->firstWhere('action', 'auth.login_failed');
        $this->assertSame(['type' => 'user', 'id' => $this->recruiter->id, 'name' => 'Rina Rekruter Senior'], $failed['actor']);
        $this->assertSame('127.0.0.1', $failed['ip_address']);
        $this->assertSame('=HYPERLINK("x")', $failed['user_agent']);
        $this->assertSame('2026-10-05T09:05:00+00:00', $failed['occurred_at']);

        $times = array_column($response->json('data'), 'occurred_at');
        $sorted = $times;
        rsort($sorted);
        $this->assertSame($sorted, $times);

        // Nothing of another company shows: its member signed in just now.
        $this->assertSame([], array_values(array_filter($response->json('data'), fn (array $row): bool => ($row['actor']['id'] ?? null) === $this->stranger->id)));
    }

    public function test_the_log_is_filtered_by_group_member_and_time(): void
    {
        $this->seedLog();

        $this->assertSame(['auth.login_failed', 'auth.login'], $this->actions($this->api('GET', '/activity?filter[group]=sign_in')->assertOk()));
        $this->assertSame(['company.member_renamed'], $this->actions($this->api('GET', '/activity?filter[group]=team')->assertOk()));
        $this->assertSame(['settings.updated'], $this->actions($this->api('GET', '/activity?filter[group]=settings')->assertOk()));
        $this->assertSame([], $this->actions($this->api('GET', '/activity?filter[group]=data_access')->assertOk()));

        $byRecruiter = $this->actions($this->api('GET', "/activity?filter[user_id]={$this->recruiter->id}")->assertOk());
        $this->assertSame(['auth.login_failed', 'auth.login'], $byRecruiter);
        $this->assertSame(['company.member_renamed'], $this->actions($this->api('GET', "/activity?filter[user_id]={$this->owner->id}")->assertOk()));

        $window = $this->api('GET', '/activity?'.http_build_query(['filter' => ['from' => '2026-10-05T09:04:00Z', 'to' => '2026-10-05T09:11:00Z']]))->assertOk();
        $this->assertSame(['company.member_renamed', 'auth.login_failed'], $this->actions($window));
    }

    public function test_the_filters_are_checked(): void
    {
        $this->api('GET', '/activity?filter[group]=nonsense')->assertStatus(422);
        $this->api('GET', '/activity?filter[color]=red')->assertStatus(422);
        $this->api('GET', '/activity?filter[user_id]=abc')->assertStatus(422);
        $this->api('GET', '/activity?filter[from]=2026-10-06&filter[to]=2026-10-05')->assertStatus(422);
        $this->api('GET', '/activity?per_page=0')->assertStatus(422);
        $this->api('GET', '/activity?cursor=not-a-cursor')->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:invalid-cursor');
    }

    public function test_the_log_is_read_in_slices(): void
    {
        $this->seedLog();

        $first = $this->api('GET', '/activity?per_page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->assertNotNull($first->json('meta.next_cursor'));
        $second = $this->api('GET', '/activity?per_page=2&cursor='.$first->json('meta.next_cursor'))->assertOk()->assertJsonCount(2, 'data');
        $this->assertNull($second->json('meta.next_cursor'));
        $this->assertSame([], array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
    }

    public function test_only_a_company_admin_reads_the_log(): void
    {
        $this->api('GET', '/activity', [], $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:company-admin-required');
        $this->api('GET', '/activity/export', [], $this->recruiterToken)->assertForbidden();

        $seeker = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'id']);
        $this->api('GET', '/activity', [], $this->tokenOf($seeker))->assertForbidden()->assertJsonPath('type', 'urn:jobportal:error:not-a-company-member');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/company/activity')->assertUnauthorized();
    }

    // ----- the export -----

    public function test_the_log_is_exported_as_csv_and_the_export_is_itself_audited(): void
    {
        $this->seedLog();

        $response = $this->api('GET', '/activity/export?filter[group]=sign_in')->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('company-activity-20261005.csv', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFTime (UTC),Actor,Actor type,Action,Subject type,Subject ID,Subject,IP address,User agent\r\n", $csv);
        $lines = array_values(array_filter(explode("\r\n", $csv)));
        $this->assertCount(3, $lines, 'the header and the two sign-in entries');
        $this->assertStringStartsWith('2026-10-05T09:05:00Z,Rina Rekruter Senior,user,auth.login_failed,user,'.$this->recruiter->id.',,127.0.0.1,', $lines[1]);
        $this->assertStringContainsString("'=HYPERLINK", $lines[1], 'a spreadsheet must not read a user agent as a formula');

        $audit = DB::table('platform_audit_logs')->where('action', 'company.activity_exported')->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->owner->id, (int) $audit->actor_id);
        $this->assertSame($this->companyId, (int) $audit->company_id);
        $this->assertSame(['group' => 'sign_in'], json_decode((string) $audit->metadata, true));

        $this->assertContains('company.activity_exported', $this->actions($this->api('GET', '/activity?filter[group]=export')->assertOk()));
    }

    public function test_an_export_of_more_than_one_slice_holds_every_entry(): void
    {
        $rows = [];

        foreach (range(1, 520) as $second) {
            $rows[] = [
                'actor_type' => 'user', 'actor_id' => $this->recruiter->id, 'action' => 'auth.login', 'subject_type' => 'user', 'subject_id' => $this->recruiter->id,
                'ip_address' => '127.0.0.1', 'occurred_at' => Carbon::parse('2026-10-04 10:00:00')->addSeconds($second)->format('Y-m-d H:i:s.u'), 'company_id' => $this->companyId,
            ];
        }

        DB::table('platform_audit_logs')->insert($rows);

        $lines = array_values(array_filter(explode("\r\n", $this->api('GET', '/activity/export?filter[group]=sign_in')->assertOk()->streamedContent())));

        $this->assertCount(521, $lines, 'the header and every entry, across the slices of the export');
        $this->assertCount(521, array_unique($lines));
    }

    public function test_the_export_is_limited_to_ten_an_hour(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->api('GET', '/activity/export')->assertOk()->streamedContent();
        }

        $this->api('GET', '/activity/export')->assertStatus(429)->assertHeader('Retry-After');
    }
}
