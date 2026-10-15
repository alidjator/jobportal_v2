<?php

namespace Tests\Feature\JobSeeker;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Src\Identity\Domain\User;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\JobSeeker\Infrastructure\Mail\TemplatedMail;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class ProfileReminderTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow('2026-10-05 03:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $overrides */
    private function seeker(string $email = 'siti@example.test', ?string $firstLoginAt = '2026-10-03 08:00:00', array $overrides = []): int
    {
        return (int) UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Siti', 'email' => $email, 'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-10-01 00:00:00',
            'first_login_at' => $firstLoginAt, ...$overrides,
        ])->id;
    }

    private function completeProfile(int $userId): void
    {
        DB::table('jobseeker_profiles')->insert(['user_id' => $userId, 'phone' => '081234567890', ...$this->locationChain(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('jobseeker_resumes')->insert(['user_id' => $userId, 'name' => 'CV', 'path' => 'resumes/cv.pdf', 'size_bytes' => 10, 'created_at' => now()]);
        DB::table('jobseeker_educations')->insert([
            'user_id' => $userId, 'education_level_id' => $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 1]), 'institution' => 'ITB', 'start_year' => 2016,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function runReminders(): string
    {
        $this->assertSame(0, Artisan::call('job-seeker:send-profile-reminders'));

        return trim(Artisan::output());
    }

    private function profile(int $userId): ?object
    {
        return DB::table('jobseeker_profiles')->where('user_id', $userId)->first();
    }

    public function test_a_first_sign_in_gets_its_reminder_on_the_following_day_naming_what_is_missing(): void
    {
        $user = $this->seeker();

        $this->assertSame('Profile reminders sent: 1', $this->runReminders());

        Mail::assertQueued(TemplatedMail::class, 1);
        Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail): bool {
            $mail->assertSeeInText('nomor telepon');
            $mail->assertSeeInText('pengalaman atau pendidikan');

            return $mail->hasTo('siti@example.test') && $mail->queue === 'bulk';
        });
        $this->assertSame(1, (int) $this->profile($user)->reminders_sent);
    }

    public function test_the_day_of_the_first_sign_in_is_too_early(): void
    {
        // 10:00 Jakarta on the 5th: the sign-in was on the same Jakarta day (the 5th, 02:00 Jakarta = 19:00 UTC on the 4th).
        $this->seeker('today@example.test', '2026-10-04 19:00:00');
        $this->seeker('never@example.test', null);

        $this->assertSame('Profile reminders sent: 0', $this->runReminders());
        Mail::assertNothingQueued();
    }

    public function test_then_every_seven_days_and_three_in_all(): void
    {
        $user = $this->seeker();

        $this->runReminders();
        $this->assertSame('Profile reminders sent: 0', $this->runReminders(), 'the same day');

        Carbon::setTestNow('2026-10-11 03:00:00');
        $this->assertSame('Profile reminders sent: 0', $this->runReminders(), 'six days later');

        Carbon::setTestNow('2026-10-12 03:00:00');
        $this->assertSame('Profile reminders sent: 1', $this->runReminders(), 'seven days later');

        Carbon::setTestNow('2026-10-19 03:00:00');
        $this->assertSame('Profile reminders sent: 1', $this->runReminders());

        Carbon::setTestNow('2026-12-01 03:00:00');
        $this->assertSame('Profile reminders sent: 0', $this->runReminders(), 'the cap is three');

        Mail::assertQueued(TemplatedMail::class, 3);
        $this->assertSame(3, (int) $this->profile($user)->reminders_sent);
    }

    public function test_a_complete_profile_gets_nothing(): void
    {
        $this->completeProfile($this->seeker());

        $this->assertSame('Profile reminders sent: 0', $this->runReminders());
        Mail::assertNothingQueued();
    }

    public function test_the_reminder_stops_once_the_profile_is_completed_and_names_only_what_is_left(): void
    {
        $user = $this->seeker();
        DB::table('jobseeker_profiles')->insert(['user_id' => $user, 'phone' => '081234567890', 'created_at' => now(), 'updated_at' => now()]);

        $this->runReminders();

        Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail): bool {
            $mail->assertDontSeeInText('nomor telepon');
            $mail->assertSeeInText('resume');

            return true;
        });
    }

    public function test_an_unverified_suspended_or_anonymised_account_is_left_alone(): void
    {
        $this->seeker('unverified@example.test', '2026-10-03 08:00:00', ['email_verified_at' => null]);
        $this->seeker('suspended@example.test', '2026-10-03 08:00:00', ['status' => 'suspended']);
        $this->seeker('gone@example.test', '2026-10-03 08:00:00', ['anonymized_at' => '2026-10-04 00:00:00', 'name' => User::ANONYMIZED_NAME]);
        UserModel::query()->create(['type' => 'company_member', 'name' => 'Budi', 'email' => 'budi@example.test', 'status' => 'active', 'locale' => 'id', 'email_verified_at' => '2026-10-01 00:00:00', 'first_login_at' => '2026-10-03 08:00:00']);

        $this->assertSame('Profile reminders sent: 0', $this->runReminders());
        Mail::assertNothingQueued();
    }

    public function test_the_job_seeker_can_switch_the_reminder_off_and_the_cap_only_counts_sent_ones(): void
    {
        $user = $this->seeker();
        DB::table('notification_preferences')->insert(['user_id' => $user, 'key' => 'profile_reminder', 'channel' => 'email', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame('Profile reminders sent: 0', $this->runReminders());
        Mail::assertNothingQueued();
        $this->assertNull($this->profile($user));
    }

    public function test_the_email_links_to_the_configured_profile_page(): void
    {
        config(['app.profile_url' => 'https://app.example.test/profile']);
        $this->seeker();

        $this->runReminders();

        Mail::assertQueued(TemplatedMail::class, function (TemplatedMail $mail): bool {
            $mail->assertSeeInText('https://app.example.test/profile');

            return true;
        });
    }
}
