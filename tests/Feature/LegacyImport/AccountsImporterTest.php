<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\LegacyImport\Application\ImportReport;
use Src\LegacyImport\Application\ImportRunner;
use Tests\Concerns\CreatesReferenceRows;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class AccountsImporterTest extends FeatureTestCase
{
    use CreatesReferenceRows;
    use UsesLegacyDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyDatabase();
    }

    private function import(bool $write = true): ImportReport
    {
        return $this->app->make(ImportRunner::class)->run('accounts', $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report): array
    {
        return $report->toArray(true)['entities']['accounts'];
    }

    private function ownerOfACompany(array $attributes = []): int
    {
        $id = $this->legacyUser(['role' => 'company', ...$attributes]);
        $this->legacyCompany($id, 1, 1);

        return $id;
    }

    // ----- what is imported and how -----

    public function test_a_job_seeker_is_imported_with_its_fields_and_remembered(): void
    {
        $hash = Hash::make('secret123');
        $id = $this->legacyUser(
            ['name' => '  Siti Rahma ', 'email' => 'Siti.Rahma@Example.TEST', 'password' => $hash, 'email_verified_at' => '2024-03-01 09:00:00', 'created_at' => '2024-02-01 07:00:00', 'updated_at' => '2024-02-05 07:00:00'],
            ['campaign_channel' => 'tiktok-ads'],
        );

        $report = $this->import();

        $user = DB::table('identity_users')->first();
        $this->assertSame('job_seeker', $user->type);
        $this->assertSame('Siti Rahma', $user->name);
        $this->assertSame('siti.rahma@example.test', $user->email);
        $this->assertSame($hash, $user->password);
        $this->assertSame('2024-03-01 09:00:00', $user->email_verified_at);
        $this->assertSame('active', $user->status);
        $this->assertSame('id', $user->locale);
        $this->assertSame('tiktok-ads', $user->registration_campaign_code);
        $this->assertSame('2024-02-01 07:00:00', $user->created_at);
        $this->assertSame('2024-02-05 07:00:00', $user->updated_at);
        $this->assertNull($user->avatar_path);
        $this->assertNull($user->last_login_at);

        $this->assertSame((int) $user->id, DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $id)->value('new_id'));
        $this->assertSame(['read' => 1, 'imported' => 1, 'skipped' => 0], array_intersect_key($this->entity($report), array_flip(['read', 'imported', 'skipped'])));
    }

    public function test_a_company_owner_with_a_company_becomes_a_company_member_account(): void
    {
        $this->ownerOfACompany(['name' => 'Andi Owner', 'email' => 'andi@example.test']);

        $this->import();

        $this->assertSame('company_member', DB::table('identity_users')->value('type'));
    }

    public function test_an_inactive_account_is_deactivated_and_an_unverified_one_stays_unverified(): void
    {
        $this->legacyUser(['status' => 0, 'email_verified_at' => null]);

        $this->import();

        $user = DB::table('identity_users')->first();
        $this->assertSame('deactivated', $user->status);
        $this->assertNull($user->email_verified_at);
    }

    public function test_only_a_bcrypt_hash_is_copied_and_anything_else_becomes_no_password(): void
    {
        $this->legacyUser(['email' => 'a@example.test', 'password' => md5('x')]);
        $this->legacyUser(['email' => 'b@example.test', 'password' => null]);
        $this->legacyUser(['email' => 'c@example.test', 'password' => password_hash('x', PASSWORD_BCRYPT)]);

        $report = $this->import();

        $this->assertSame([null, null, true], DB::table('identity_users')->orderBy('id')->get()->map(fn ($u): ?bool => $u->password === null ? null : true)->all());
        $this->assertSame(2, $this->entity($report)['notes']['password-not-bcrypt']);
    }

    public function test_the_default_campaign_value_and_a_long_one_are_handled(): void
    {
        $this->legacyUser(['email' => 'a@example.test'], ['campaign_channel' => 'nosession']);
        $this->legacyUser(['email' => 'b@example.test'], ['campaign_channel' => str_repeat('x', 100)]);
        $this->legacyUser(['email' => 'c@example.test'], ['campaign_channel' => null]);

        $this->import();

        $codes = DB::table('identity_users')->orderBy('id')->pluck('registration_campaign_code')->all();
        $this->assertSame([null, str_repeat('x', 64), null], $codes);
    }

    // ----- what is skipped -----

    public function test_rows_that_cannot_be_imported_are_skipped_with_their_reason_and_an_odd_email_is_kept_as_it_is(): void
    {
        $demo = $this->legacyUser(['email' => 'demo@example.test', 'is_demo_field' => 1]);
        $unknown = $this->legacyUser(['email' => 'unknown@example.test', 'role' => 'robot']);
        $orphanOwner = $this->legacyUser(['email' => 'owner@example.test', 'role' => 'company']);
        $badEmail = $this->legacyUser(['email' => 'not-an-email']);
        $noName = $this->legacyUser(['email' => 'noname@example.test', 'name' => '   ']);
        $first = $this->legacyUser(['email' => 'same@example.test']);
        $second = $this->legacyUser(['email' => 'SAME@example.test']);
        DB::table('identity_users')->insert(['type' => 'job_seeker', 'name' => 'Existing', 'email' => 'taken@example.test', 'status' => 'active', 'locale' => 'id', 'created_at' => now(), 'updated_at' => now()]);
        $taken = $this->legacyUser(['email' => 'taken@example.test']);

        $report = $this->entity($this->import());

        $reasons = collect($report['skipped_rows'])->pluck('reason', 'legacy_id')->all();
        $this->assertSame([
            $demo => 'demo-account',
            $unknown => 'unknown-role',
            $orphanOwner => 'company-owner-without-company',
            $noName => 'name-missing',
            $second => 'email-taken',
            $taken => 'email-taken',
        ], $reasons);
        $this->assertSame(8, $report['read']);
        $this->assertSame(2, $report['imported']);
        $this->assertSame(['not-an-email'], DB::table('identity_users')->where('email', 'not-an-email')->pluck('email')->all());
        $this->assertSame(['same@example.test'], DB::table('identity_users')->where('email', 'same@example.test')->pluck('email')->all());
        $this->assertNotNull(DB::table('legacyimport_id_map')->where('entity', 'user')->where('legacy_id', $first)->first());
    }

    // ----- safe to repeat, and a dry run -----

    public function test_running_it_again_adds_nothing_and_never_overwrites(): void
    {
        $id = $this->legacyUser(['email' => 'a@example.test']);
        $this->import();
        DB::table('identity_users')->update(['name' => 'Changed in the new system']);
        $this->legacyUser(['email' => 'b@example.test']);

        $report = $this->import();

        $this->assertSame(2, DB::table('identity_users')->count());
        $this->assertSame('Changed in the new system', DB::table('identity_users')->where('email', 'a@example.test')->value('name'));
        $this->assertSame(1, $this->entity($report)['notes']['already-imported']);
        $this->assertSame(1, $this->entity($report)['imported']);
        $this->assertNotNull(DB::table('legacyimport_id_map')->where('legacy_id', $id)->first());
    }

    public function test_a_dry_run_reports_but_writes_nothing(): void
    {
        $this->legacyUser(['email' => 'a@example.test']);
        $this->legacyUser(['email' => 'demo@example.test', 'is_demo_field' => 1]);

        $report = $this->entity($this->import(false));

        $this->assertSame(['read' => 2, 'imported' => 1, 'skipped' => 1], array_intersect_key($report, array_flip(['read', 'imported', 'skipped'])));
        $this->assertSame(0, DB::table('identity_users')->count());
        $this->assertSame(0, DB::table('legacyimport_id_map')->count());
    }

    public function test_a_dry_run_still_notices_a_duplicate_inside_the_legacy_data(): void
    {
        $this->legacyUser(['email' => 'same@example.test']);
        $second = $this->legacyUser(['email' => 'Same@example.test']);

        $report = $this->entity($this->import(false));

        $this->assertSame([['legacy_id' => $second, 'reason' => 'email-taken']], $report['skipped_rows']);
    }

    // ----- the result seen through the API -----

    public function test_an_imported_account_signs_in_with_its_old_password_and_must_accept_the_terms(): void
    {
        $this->legacyUser(['email' => 'siti@example.test', 'password' => Hash::make('secret123'), 'email_verified_at' => '2024-03-01 09:00:00']);
        $this->legacyUser(['email' => 'nopass@example.test', 'password' => null]);
        $this->import();

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => 'secret123'])->assertOk();
        $this->assertSame('siti@example.test', $login->json('data.user.email'));

        $this->postJson('/api/v1/auth/login', ['email' => 'nopass@example.test', 'password' => 'anything'])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => 'wrong-password'])->assertStatus(401);
    }

    public function test_a_deactivated_account_is_refused_at_sign_in(): void
    {
        $this->legacyUser(['email' => 'old@example.test', 'password' => Hash::make('secret123'), 'status' => 0]);
        $this->import();

        $this->postJson('/api/v1/auth/login', ['email' => 'old@example.test', 'password' => 'secret123'])->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:account-deactivated');
    }
}
