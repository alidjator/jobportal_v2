<?php

namespace Tests\Feature\JobAlert;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsJobAlertFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-activation.md` section 4: the first sign-in of an imported account moves the cursor of its alerts, so no backlog is sent. */
final class StartAlertsAtFirstSignInTest extends FeatureTestCase
{
    use BuildsJobAlertFixtures;

    private const string PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobAlertFixtures();
        DB::table('identity_users')->where('id', $this->seekerId)->update(['password' => Hash::make(self::PASSWORD), 'imported_at' => '2026-10-05 08:00:00']);
    }

    protected function tearDown(): void
    {
        $this->tearDownJobAlertFixtures();

        parent::tearDown();
    }

    private function signIn(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.test', 'password' => self::PASSWORD])->assertOk();
    }

    private function cursor(int $alert): string
    {
        return (string) DB::table('jobalert_alerts')->where('id', $alert)->value('last_run_at');
    }

    public function test_the_first_sign_in_moves_the_cursor_of_the_alerts_to_that_moment(): void
    {
        $alert = $this->alertId();
        $this->assertSame('2026-10-05 09:00:00', $this->cursor($alert));

        Carbon::setTestNow('2026-10-12 10:30:00');
        $this->signIn();

        $this->assertSame('2026-10-12 10:30:00', $this->cursor($alert));
        $this->assertNotNull(DB::table('identity_users')->where('id', $this->seekerId)->value('first_login_at'));
    }

    public function test_a_later_sign_in_leaves_the_cursor_alone(): void
    {
        $alert = $this->alertId();

        Carbon::setTestNow('2026-10-12 10:30:00');
        $this->signIn();
        Carbon::setTestNow('2026-10-19 10:30:00');
        $this->signIn();

        $this->assertSame('2026-10-12 10:30:00', $this->cursor($alert));
    }

    public function test_the_alerts_of_other_accounts_are_not_touched(): void
    {
        $other = $this->seeker('other@example.test');
        $otherAlert = $this->alertId(token: $this->tokenFor($other));

        Carbon::setTestNow('2026-10-12 10:30:00');
        $this->signIn();

        $this->assertSame('2026-10-05 09:00:00', $this->cursor($otherAlert));
    }
}
