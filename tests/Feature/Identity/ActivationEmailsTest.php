<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Src\Identity\Application\IdentitySettings;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Platform\Application\PlatformSettings;
use Src\Platform\Application\Settings\SettingsStore;
use Symfony\Component\Mime\Email;
use Tests\FeatureTestCase;

/** `identity:send-activation-emails`: the activation email, its reminder and the guards (`docs/design/phase-8-activation.md` section 3). */
final class ActivationEmailsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-02 09:00:00');
        config([
            'app.job_seeker_activation_url' => 'https://karir.example/aktifkan-akun',
            'app.company_activation_url' => 'https://admin.example/aktifkan-akun',
            'app.job_seeker_sign_in_url' => 'https://karir.example/masuk',
        ]);
        $this->setting(PlatformSettings::SUPPORT_EMAIL, 'bantuan@example.test');
        $this->setting(IdentitySettings::ACTIVATION_JOB_SEEKERS_NOT_BEFORE, '2026-11-02');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function setting(string $key, mixed $value): void
    {
        $this->app->make(SettingsStore::class)->setGlobal($key, $value, null);
    }

    /** An imported account that has not signed in yet. @param  array<string, mixed>  $attributes */
    private function imported(string $email, array $attributes = []): UserModel
    {
        return UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Siti', 'email' => $email, 'password' => 'hash', 'status' => 'active', 'locale' => 'id',
            'email_verified_at' => now(), 'imported_at' => '2026-10-30 00:00:00', ...$attributes,
        ]);
    }

    private function suppress(string $email, string $reason = 'hard_bounce'): void
    {
        DB::table('platform_email_suppressions')->insert(['email' => $email, 'reason' => $reason, 'source' => 'test', 'created_at' => now()]);
    }

    /** @return list<Email> */
    private function emails(): array
    {
        return array_map(
            fn ($message): Email => $message->getOriginalMessage(),
            $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all(),
        );
    }

    /** @return list<string> */
    private function recipients(): array
    {
        return array_map(fn (Email $email): string => $email->getTo()[0]->getAddress(), $this->emails());
    }

    /** @param  array<string, mixed>  $options */
    private function send(array $options = [], int $limit = 200): PendingCommand
    {
        $command = $this->artisan('identity:send-activation-emails', ['--audience' => 'job_seeker', '--write' => true, '--delay' => 0, '--limit' => $limit, ...$options]);

        return $command->expectsConfirmation("Send up to {$limit} ".(isset($options['--reminders']) ? 'reminder' : 'activation').' emails?', 'yes');
    }

    public function test_a_dry_run_counts_and_sends_nothing(): void
    {
        $this->imported('a@example.test');
        $this->imported('b@example.test');

        $this->artisan('identity:send-activation-emails', ['--audience' => 'job_seeker'])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame([], $this->emails());
        $this->assertSame(0, DB::table('identity_activation_emails')->count());
    }

    public function test_it_needs_a_valid_audience_and_a_limit(): void
    {
        $this->artisan('identity:send-activation-emails')->expectsOutputToContain('--audience')->assertFailed();
        $this->artisan('identity:send-activation-emails', ['--audience' => 'job_seeker', '--limit' => 0])->expectsOutputToContain('at least 1')->assertFailed();
    }

    public function test_it_sends_only_to_imported_active_accounts_that_have_not_signed_in_and_not_been_emailed(): void
    {
        $this->imported('a@example.test');
        $this->imported('gone@example.test');
        $this->suppress('gone@example.test');
        $this->imported('off@example.test', ['status' => 'deactivated']);
        $this->imported('signed-in@example.test', ['first_login_at' => '2026-11-01 08:00:00']);
        $this->imported('native@example.test', ['imported_at' => null]);
        $this->imported('anonymised@example.test', ['anonymized_at' => now()]);
        $this->imported('company@example.test', ['type' => 'company_member']);

        $this->send()->assertSuccessful();
        $this->assertSame(['a@example.test'], $this->recipients());

        $row = DB::table('identity_activation_emails')->first();
        $this->assertSame(['activation', 'has_password'], [$row->kind, $row->variant]);
        $this->assertStringStartsWith('batch-job_seeker-', $row->batch);

        $this->send()->assertSuccessful();
        $this->assertSame(['a@example.test'], $this->recipients(), 'A second run sends nothing twice.');
    }

    public function test_the_two_job_seeker_variants_get_their_own_text_and_never_a_code(): void
    {
        $this->imported('has@example.test');
        $this->imported('none@example.test', ['password' => null]);

        $this->send()->assertSuccessful();

        $bySubject = [];
        foreach ($this->emails() as $email) {
            $bySubject[$email->getTo()[0]->getAddress()] = $email;
        }

        $this->assertSame('Akun karir.pkp Anda kini ada di ONESIS Career', $bySubject['has@example.test']->getSubject());
        $this->assertSame('Aktifkan akun karir.pkp Anda di ONESIS Career', $bySubject['none@example.test']->getSubject());

        foreach ($bySubject as $email) {
            $body = (string) $email->getTextBody();
            $this->assertStringContainsString('https://karir.example/aktifkan-akun', $body);
            $this->assertStringContainsString('bantuan@example.test', $body);
            $this->assertSame(0, preg_match('/\b\d{6}\b/', $body), 'The email holds no code.');
        }

        $this->assertSame(['has_password', 'no_password'], DB::table('identity_activation_emails')->orderBy('id')->pluck('variant')->all());
    }

    public function test_verified_addresses_go_first_and_unverified_ones_wait_until_none_is_left(): void
    {
        $this->imported('unverified@example.test', ['email_verified_at' => null]);
        $this->imported('a@example.test');
        $this->imported('b@example.test');

        $this->send(limit: 1)->assertSuccessful();
        $this->assertSame(['a@example.test'], $this->recipients());

        $this->send(limit: 5)->assertSuccessful();
        $this->assertSame(['a@example.test', 'b@example.test'], $this->recipients(), 'The unverified address is still held back.');

        $this->send(limit: 5)->assertSuccessful();
        $this->assertSame(['a@example.test', 'b@example.test', 'unverified@example.test'], $this->recipients());
    }

    public function test_the_company_audience_sends_the_company_variant_and_reports_how_many_owners_signed_in(): void
    {
        $this->imported('owner@example.test', ['type' => 'company_member', 'name' => 'Andi']);
        $this->imported('owner2@example.test', ['type' => 'company_member', 'first_login_at' => '2026-11-01 08:00:00']);

        $this->artisan('identity:send-activation-emails', ['--audience' => 'company', '--write' => true, '--delay' => 0])
            ->expectsConfirmation('Send up to 200 activation emails?', 'yes')
            ->expectsOutputToContain('Company owners that have signed in')
            ->assertSuccessful();

        $this->assertSame(['owner@example.test'], $this->recipients());
        $this->assertSame('company', DB::table('identity_activation_emails')->value('variant'));
        $this->assertStringContainsString('https://admin.example/aktifkan-akun', (string) $this->emails()[0]->getTextBody());
    }

    // ----- guards -----

    public function test_a_real_run_is_refused_while_the_support_address_is_empty(): void
    {
        $this->setting(PlatformSettings::SUPPORT_EMAIL, '');
        $this->imported('a@example.test');

        $this->send()->expectsOutputToContain('platform.support_email')->assertFailed();

        $this->artisan('identity:send-activation-emails', ['--audience' => 'job_seeker'])
            ->expectsOutputToContain('A real run would be refused')
            ->assertSuccessful();
        $this->assertSame([], $this->emails());
    }

    public function test_a_real_run_is_refused_while_a_page_url_is_not_configured(): void
    {
        config(['app.job_seeker_activation_url' => '']);
        $this->imported('a@example.test');

        $this->send()->expectsOutputToContain('URLs')->assertFailed();
        $this->assertSame([], $this->emails());
    }

    public function test_job_seekers_are_not_emailed_before_the_set_date_or_while_it_is_empty(): void
    {
        $this->imported('a@example.test');

        $this->setting(IdentitySettings::ACTIVATION_JOB_SEEKERS_NOT_BEFORE, '2026-11-03');
        $this->send()->expectsOutputToContain('2026-11-03')->assertFailed();

        $this->setting(IdentitySettings::ACTIVATION_JOB_SEEKERS_NOT_BEFORE, '');
        $this->send()->expectsOutputToContain('empty')->assertFailed();

        $this->assertSame([], $this->emails());
    }

    public function test_the_company_audience_does_not_wait_for_the_job_seeker_date(): void
    {
        $this->setting(IdentitySettings::ACTIVATION_JOB_SEEKERS_NOT_BEFORE, '');
        $this->imported('owner@example.test', ['type' => 'company_member']);

        $this->artisan('identity:send-activation-emails', ['--audience' => 'company', '--write' => true, '--delay' => 0])
            ->expectsConfirmation('Send up to 200 activation emails?', 'yes')
            ->assertSuccessful();

        $this->assertSame(['owner@example.test'], $this->recipients());
    }

    public function test_the_stop_rule_refuses_the_next_batch_when_the_previous_one_bounced_above_the_limit(): void
    {
        foreach (range(1, 6) as $n) {
            $this->imported("p{$n}@example.test");
        }

        $this->send(['--pilot' => true], 5)->assertSuccessful();
        $this->assertCount(5, $this->emails());
        $this->assertStringStartsWith('pilot-job_seeker-', DB::table('identity_activation_emails')->value('batch'));

        $this->suppress('p1@example.test');

        $this->send(limit: 5)->expectsOutputToContain('previous batch')->assertFailed();
        $this->assertCount(5, $this->emails());
    }

    public function test_the_stop_rule_lets_the_next_batch_go_when_the_previous_one_stayed_within_the_limits(): void
    {
        $this->setting(IdentitySettings::ACTIVATION_BOUNCE_LIMIT_PERCENT, '20');
        foreach (range(1, 6) as $n) {
            $this->imported("p{$n}@example.test");
        }

        $this->send(limit: 5)->assertSuccessful();
        $this->suppress('p1@example.test');

        $this->send(limit: 5)->assertSuccessful();
        $this->assertCount(6, $this->emails());
    }

    public function test_a_complaint_stops_the_next_batch_too(): void
    {
        foreach (range(1, 6) as $n) {
            $this->imported("p{$n}@example.test");
        }

        $this->send(limit: 5)->assertSuccessful();
        $this->suppress('p2@example.test', 'complaint');

        $this->send(limit: 5)->expectsOutputToContain('complaints')->assertFailed();
    }

    // ----- reminders -----

    public function test_the_reminder_goes_once_after_the_set_days_to_accounts_that_still_have_not_signed_in(): void
    {
        $waiting = $this->imported('waiting@example.test');
        $this->imported('came@example.test');
        $this->imported('fresh@example.test');

        $this->send()->assertSuccessful();
        $this->assertCount(3, $this->emails());

        DB::table('identity_activation_emails')->whereIn('user_id', [$waiting->id, UserModel::query()->where('email', 'came@example.test')->value('id')])->update(['sent_at' => '2026-10-25 09:00:00']);
        UserModel::query()->where('email', 'came@example.test')->update(['first_login_at' => '2026-10-30 10:00:00']);

        $this->send(['--reminders' => true])->assertSuccessful();
        $this->assertSame(['waiting@example.test'], array_slice($this->recipients(), 3));
        $this->assertSame('Akun karir.pkp Anda masih menunggu di ONESIS Career', $this->emails()[3]->getSubject());
        $this->assertSame('reminder', DB::table('identity_activation_emails')->where('user_id', $waiting->id)->where('kind', 'reminder')->value('kind'));

        $this->send(['--reminders' => true])->assertSuccessful();
        $this->assertCount(4, $this->emails(), 'The reminder is sent once.');
    }

    // ----- staff test -----

    public function test_the_staff_list_limits_the_run_to_its_addresses_and_logs_manual_emails_that_do_not_use_up_the_real_one(): void
    {
        $this->setting(IdentitySettings::ACTIVATION_JOB_SEEKERS_NOT_BEFORE, '2026-12-01');
        $this->imported('staff@example.test');
        $this->imported('other@example.test');
        $file = tempnam(sys_get_temp_dir(), 'staff');
        file_put_contents($file, "Staff@Example.test\n\n");

        $this->send(['--emails' => $file])->assertSuccessful();

        $this->assertSame(['staff@example.test'], $this->recipients());
        $this->assertSame(['manual'], DB::table('identity_activation_emails')->pluck('kind')->all());

        $this->send(['--emails' => $file])->assertSuccessful();
        $this->assertCount(2, $this->emails(), 'A staff test may be repeated.');

        $this->setting(IdentitySettings::ACTIVATION_JOB_SEEKERS_NOT_BEFORE, '2026-11-02');
        $this->send()->assertSuccessful();
        $this->assertCount(4, $this->emails(), 'The real activation still reaches the staff account.');
        unlink($file);
    }

    public function test_an_unreadable_staff_file_is_refused(): void
    {
        $this->artisan('identity:send-activation-emails', ['--audience' => 'job_seeker', '--emails' => '/nonexistent/list.txt'])->expectsOutputToContain('could not be read')->assertFailed();
    }
}
