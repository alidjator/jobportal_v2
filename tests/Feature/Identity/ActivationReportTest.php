<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/phase-8-activation.md` section 10 and 12: the activation report, the filters and detail of `admin/users`, the card of the admin dashboard and the console report. */
final class ActivationReportTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private string $token;

    /** @var array<string, UserModel> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 09:00:00');
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->token = $this->adminToken(['users.view', 'dashboard.view']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email = 'ops@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addDays(60)->addHour())->plainTextToken;
    }

    /** @param  array<string, mixed>  $attributes */
    private function user(string $key, array $attributes = []): UserModel
    {
        return $this->users[$key] = UserModel::query()->create([
            'type' => 'job_seeker', 'name' => ucfirst($key), 'email' => "{$key}@example.test", 'password' => 'hash', 'status' => 'active', 'locale' => 'id',
            'email_verified_at' => '2026-10-01 00:00:00', 'imported_at' => '2026-10-30 00:00:00', ...$attributes,
        ]);
    }

    private function sent(UserModel $user, string $batch, string $at, string $kind = 'activation', string $variant = 'has_password'): void
    {
        DB::table('identity_activation_emails')->insert(['user_id' => $user->id, 'kind' => $kind, 'variant' => $variant, 'batch' => $batch, 'sent_at' => $at]);
    }

    private function suppress(string $email, string $reason = 'hard_bounce'): void
    {
        DB::table('platform_email_suppressions')->insert(['email' => $email, 'reason' => $reason, 'source' => 'test', 'created_at' => now()]);
    }

    /** @param  array<string, mixed>  $query */
    private function admin(string $uri, array $query = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->getJson('/api/v1/admin'.$uri.($query === [] ? '' : '?'.http_build_query($query)));
    }

    /** Five imported job seekers: a signed in, b sent and bounced, c deactivated, d unverified, e not sent; and a native one who signed in. */
    private function seekers(): void
    {
        $a = $this->user('a', ['first_login_at' => '2026-11-05 10:00:00']);
        $b = $this->user('b');
        $this->user('c', ['status' => 'deactivated']);
        $this->user('d', ['email_verified_at' => null]);
        $this->user('e');
        $this->user('native', ['imported_at' => null, 'first_login_at' => '2026-11-06 10:00:00']);
        $this->sent($a, 'pilot-job_seeker-20261102-090000', '2026-11-02 09:00:00');
        $this->sent($b, 'pilot-job_seeker-20261102-090000', '2026-11-02 09:00:00');
        $this->suppress('b@example.test');
    }

    public function test_the_report_counts_the_accounts_the_emails_and_the_sign_ins(): void
    {
        $this->seekers();

        $report = $this->admin('/activation/report', ['audience' => 'job_seeker'])->assertOk();

        $report->assertJsonPath('data.audience', 'job_seeker')
            ->assertJsonPath('data.imported', 5)
            ->assertJsonPath('data.deactivated', 1)
            ->assertJsonPath('data.unverified', 1)
            ->assertJsonPath('data.suppressed', 1)
            ->assertJsonPath('data.eligible', 2)
            ->assertJsonPath('data.activation.first_sent_at', '2026-11-02T09:00:00+00:00')
            ->assertJsonPath('data.activation.sent', 2)
            ->assertJsonPath('data.activation.reminders_sent', 0)
            ->assertJsonPath('data.activation.bounced', 1)
            ->assertJsonPath('data.activation.complaints', 0)
            ->assertJsonPath('data.signed_in.count', 1)
            ->assertJsonPath('data.signed_in.percent_of_sent', 50)
            ->assertJsonPath('data.signed_in.percent_of_imported', 20)
            ->assertJsonCount(1, 'data.batches')
            ->assertJsonPath('data.batches.0.batch', 'pilot-job_seeker-20261102-090000')
            ->assertJsonPath('data.batches.0.sent', 2)
            ->assertJsonPath('data.batches.0.bounced', 1)
            ->assertJsonPath('data.batches.0.signed_in', 1);
        $this->assertArrayNotHasKey('companies_not_signed_in', $report->json('data'));
    }

    public function test_the_milestones_count_from_the_first_email_and_wait_until_the_day_is_reached(): void
    {
        $this->seekers();

        $early = $this->admin('/activation/report', ['audience' => 'job_seeker'])->assertOk();
        $this->assertSame([30, 60, 90], array_column($early->json('data.milestones'), 'day'));
        $this->assertSame([false, false, false], array_column($early->json('data.milestones'), 'reached'));
        $this->assertSame([null, null, null], array_column($early->json('data.milestones'), 'signed_in'));
        $this->assertSame('2026-12-02T09:00:00+00:00', $early->json('data.milestones.0.date'));

        $this->user('late', ['first_login_at' => '2026-12-10 10:00:00']);
        Carbon::setTestNow('2027-01-15 09:00:00');
        $late = $this->admin('/activation/report', ['audience' => 'job_seeker'])->assertOk();

        $this->assertSame([true, true, false], array_column($late->json('data.milestones'), 'reached'));
        $this->assertSame([1, 2, null], array_column($late->json('data.milestones'), 'signed_in'));
        $this->assertEquals([50, 100, null], array_column($late->json('data.milestones'), 'percent_of_sent'));
    }

    public function test_a_report_before_the_first_email_has_no_milestones(): void
    {
        $this->user('a');

        $this->admin('/activation/report', ['audience' => 'job_seeker'])->assertOk()
            ->assertJsonPath('data.activation.first_sent_at', null)
            ->assertJsonPath('data.signed_in.percent_of_sent', null)
            ->assertJsonPath('data.milestones', [])
            ->assertJsonPath('data.batches', []);
    }

    public function test_the_reminder_and_complaints_are_counted(): void
    {
        $a = $this->user('a');
        $b = $this->user('b');
        $this->sent($a, 'batch-job_seeker-20261102-090000', '2026-11-02 09:00:00');
        $this->sent($b, 'batch-job_seeker-20261102-090000', '2026-11-02 09:00:00');
        $this->sent($a, 'reminder-job_seeker-20261109-090000', '2026-11-09 09:00:00', 'reminder');
        $this->sent($b, 'staff-job_seeker-20261101-090000', '2026-11-01 09:00:00', 'manual');
        $this->suppress('a@example.test', 'complaint');

        $report = $this->admin('/activation/report', ['audience' => 'job_seeker'])->assertOk();

        $report->assertJsonPath('data.activation.sent', 2)->assertJsonPath('data.activation.reminders_sent', 1)->assertJsonPath('data.activation.complaints', 1)->assertJsonPath('data.activation.first_sent_at', '2026-11-02T09:00:00+00:00');
        $this->assertSame(['batch-job_seeker-20261102-090000', 'reminder-job_seeker-20261109-090000'], array_column($report->json('data.batches'), 'batch'));
    }

    public function test_the_company_report_names_the_companies_not_signed_in(): void
    {
        foreach (['PT Beta' => false, 'PT Alfa' => false, 'PT Gamma' => true] as $name => $signedIn) {
            $companyId = (int) DB::table('company_companies')->insertGetId([
                'name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name)), 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $owner = $this->user('owner'.$companyId, ['type' => 'company_member', 'first_login_at' => $signedIn ? '2026-11-05 10:00:00' : null]);
            CompanyMemberRows::insert($companyId, $owner->id, 'company_admin');
            $this->sent($owner, 'batch-company_member-20261102-090000', '2026-11-02 09:00:00', 'activation', 'company');
        }

        $report = $this->admin('/activation/report', ['audience' => 'company_member'])->assertOk();

        $report->assertJsonPath('data.imported', 3)->assertJsonPath('data.signed_in.count', 1)->assertJsonPath('data.signed_in.percent_of_imported', 33.3);
        $this->assertSame(['PT Alfa', 'PT Beta'], array_column($report->json('data.companies_not_signed_in'), 'name'));
    }

    public function test_the_report_needs_the_view_permission_and_a_known_audience(): void
    {
        $this->admin('/activation/report', ['audience' => 'job_seeker'], $this->adminToken(['dashboard.view'], 'plain@example.test'))->assertForbidden();
        $this->admin('/activation/report')->assertStatus(422)->assertJsonValidationErrors(['audience']);
        $this->admin('/activation/report', ['audience' => 'psychologist'])->assertStatus(422)->assertJsonValidationErrors(['audience']);

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/v1/admin/activation/report?audience=job_seeker')->assertUnauthorized();
    }

    public function test_the_user_list_filters_by_origin_and_by_sign_in(): void
    {
        $this->seekers();

        $emails = fn (array $query): array => collect($this->admin('/users', $query)->assertOk()->json('data'))->pluck('email')->sort()->values()->all();

        $this->assertSame(['native@example.test'], $emails(['filter' => ['imported' => 'false']]));
        $this->assertSame(['a@example.test', 'b@example.test', 'c@example.test', 'd@example.test', 'e@example.test'], $emails(['filter' => ['imported' => 'true']]));
        $this->assertSame(['a@example.test', 'native@example.test'], $emails(['filter' => ['signed_in' => 'true']]));
        $this->assertSame(['b@example.test', 'c@example.test', 'd@example.test', 'e@example.test'], $emails(['filter' => ['imported' => 'true', 'signed_in' => 'false']]));
        $this->assertCount(6, $emails([]));
        $this->admin('/users', ['filter' => ['imported' => 'maybe']])->assertStatus(422);
    }

    public function test_the_detail_shows_the_import_time_and_the_activation_emails(): void
    {
        $this->seekers();
        $this->sent($this->users['a'], 'reminder-job_seeker-20261109-090000', '2026-11-09 09:00:00', 'reminder');

        $detail = $this->admin('/users/'.$this->users['a']->id)->assertOk();

        $detail->assertJsonPath('data.imported_at', '2026-10-30T00:00:00+00:00')
            ->assertJsonPath('data.activation.0.kind', 'activation')
            ->assertJsonPath('data.activation.1.batch', 'reminder-job_seeker-20261109-090000')
            ->assertJsonPath('data.activation.1.sent_at', '2026-11-09T09:00:00+00:00');

        $native = $this->admin('/users/'.$this->users['native']->id)->assertOk();
        $native->assertJsonPath('data.imported_at', null)->assertJsonPath('data.activation', []);
        $this->assertNull($this->admin('/users', ['filter' => ['imported' => 'false']])->json('data.0.activation'));
    }

    public function test_the_dashboard_card_gives_the_two_percentages_to_admins_with_the_view_permission(): void
    {
        $this->seekers();

        $cards = $this->admin('/dashboard')->assertOk()->json('data.cards');

        $this->assertEquals(['job_seekers_signed_in_percent' => 20, 'companies_signed_in_percent' => null], $cards['activation']);

        $plain = $this->admin('/dashboard', [], $this->adminToken(['dashboard.view'], 'plain@example.test'))->assertOk()->json('data.cards');
        $this->assertArrayNotHasKey('activation', $plain);
    }

    public function test_the_console_prints_the_same_report(): void
    {
        $this->seekers();

        $this->artisan('identity:activation-report', ['--audience' => 'job_seeker'])
            ->expectsOutputToContain('Job seekers')
            ->expectsOutputToContain('pilot-job_seeker-20261102-090000')
            ->assertSuccessful();
        $this->artisan('identity:activation-report')->expectsOutputToContain('Company owners')->assertSuccessful();
        $this->artisan('identity:activation-report', ['--audience' => 'nobody'])->assertFailed();
    }
}
