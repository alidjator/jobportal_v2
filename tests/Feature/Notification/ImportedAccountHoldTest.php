<?php

namespace Tests\Feature\Notification;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Notifications\NotificationPreferences;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-activation.md` section 4: an imported account gets no scheduled email until its first sign-in. */
final class ImportedAccountHoldTest extends FeatureTestCase
{
    private NotificationPreferences $preferences;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(NotificationPreferenceCatalog::class)->add(
            new NotificationPreferenceDefinition('probe_digest', 'A digest the platform sends on its own', ['email' => true, 'push' => true], scheduled: true),
            new NotificationPreferenceDefinition('probe_reply', 'A reply to something the person did', ['email' => true, 'push' => true]),
        );
        $this->preferences = $this->app->make(NotificationPreferences::class);
    }

    private function account(string $email, ?string $importedAt, ?string $firstLoginAt = null): int
    {
        return (int) UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Siti', 'email' => $email, 'status' => 'active', 'locale' => 'en',
            'imported_at' => $importedAt, 'first_login_at' => $firstLoginAt,
        ])->id;
    }

    public function test_a_held_account_gets_no_scheduled_email(): void
    {
        $held = $this->account('held@example.test', '2026-10-01 00:00:00');

        $this->assertFalse($this->preferences->isEnabled($held, 'probe_digest', NotificationChannel::Email));
        $this->assertSame([], $this->preferences->enabledAmong([$held], 'probe_digest', NotificationChannel::Email));
    }

    public function test_an_email_caused_by_a_person_still_goes_to_a_held_account(): void
    {
        $held = $this->account('held@example.test', '2026-10-01 00:00:00');

        $this->assertTrue($this->preferences->isEnabled($held, 'probe_reply', NotificationChannel::Email));
        $this->assertSame([$held], $this->preferences->enabledAmong([$held], 'probe_reply', NotificationChannel::Email));
    }

    public function test_push_is_not_held(): void
    {
        $held = $this->account('held@example.test', '2026-10-01 00:00:00');

        $this->assertTrue($this->preferences->isEnabled($held, 'probe_digest', NotificationChannel::Push));
        $this->assertSame([$held], $this->preferences->enabledAmong([$held], 'probe_digest', NotificationChannel::Push));
    }

    public function test_the_first_sign_in_releases_the_account(): void
    {
        $account = $this->account('siti@example.test', '2026-10-01 00:00:00');

        $this->assertFalse($this->preferences->isEnabled($account, 'probe_digest', NotificationChannel::Email));

        DB::table('identity_users')->where('id', $account)->update(['first_login_at' => '2026-10-02 08:00:00']);

        $this->assertTrue($this->preferences->isEnabled($account, 'probe_digest', NotificationChannel::Email));
        $this->assertSame([$account], $this->preferences->enabledAmong([$account], 'probe_digest', NotificationChannel::Email));
    }

    public function test_an_account_that_was_not_imported_is_never_held(): void
    {
        $account = $this->account('new@example.test', null);

        $this->assertTrue($this->preferences->isEnabled($account, 'probe_digest', NotificationChannel::Email));
    }

    public function test_a_list_keeps_only_the_accounts_that_are_not_held_and_still_wanted(): void
    {
        $held = $this->account('held@example.test', '2026-10-01 00:00:00');
        $released = $this->account('released@example.test', '2026-10-01 00:00:00', '2026-10-02 08:00:00');
        $native = $this->account('native@example.test', null, '2026-10-02 08:00:00');
        $optedOut = $this->account('out@example.test', null, '2026-10-02 08:00:00');
        DB::table('notification_preferences')->insert(['user_id' => $optedOut, 'key' => 'probe_digest', 'channel' => 'email', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame([$released, $native], $this->preferences->enabledAmong([$held, $released, $native, $optedOut], 'probe_digest', NotificationChannel::Email));
    }

    public function test_the_scheduled_emails_of_the_platform_carry_the_flag(): void
    {
        $catalog = $this->app->make(NotificationPreferenceCatalog::class);

        foreach (['recommendation', 'profile_reminder', 'job_alert', 'chat_message', 'team_daily_digest'] as $key) {
            $this->assertTrue($catalog->find($key)?->scheduled, "{$key} must be a scheduled email.");
        }

        foreach (['application_update', 'invitation'] as $key) {
            $this->assertFalse($catalog->find($key)?->scheduled, "{$key} is caused by a person and must not be held.");
        }
    }
}
