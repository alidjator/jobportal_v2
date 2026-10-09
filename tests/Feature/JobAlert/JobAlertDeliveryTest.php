<?php

namespace Tests\Feature\JobAlert;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Job\Domain\Events\JobDecided;
use Src\Job\Domain\Events\JobStatusChanged;
use Src\JobAlert\Infrastructure\Mail\AlertDigestMail;
use Tests\Concerns\BuildsJobAlertFixtures;
use Tests\FeatureTestCase;

final class JobAlertDeliveryTest extends FeatureTestCase
{
    use BuildsJobAlertFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobAlertFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobAlertFixtures();

        parent::tearDown();
    }

    /** Time passes after the alert was made: a job counts only when it first went live after the alert started. */
    private function later(string $time = '2026-10-05 09:05:00'): void
    {
        Carbon::setTestNow($time);
    }

    private function runDigests(): string
    {
        $this->assertSame(0, Artisan::call('jobalert:send-digests'));

        return trim(Artisan::output());
    }

    // ----- immediate alerts -----

    public function test_a_job_published_through_the_api_notifies_the_matching_immediate_alerts_once(): void
    {
        Mail::fake();
        $alert = $this->alertId();
        $this->alertId(['name' => 'Remote only', 'criteria' => ['work_mode' => ['remote']]]);
        $this->later();

        $job = (int) $this->api('POST', '/company/jobs', $this->fullJobBody('Senior Backend Developer'))->assertCreated()->json('data.id');
        $this->api('POST', "/company/jobs/{$job}/publish")->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertSame([['type' => 'job_alert.matches', 'data' => ['alert_id' => $alert, 'job_ids' => [$job], 'total' => 1]]], $this->alertNotifications());
        Mail::assertQueued(AlertDigestMail::class, 1);
        Mail::assertQueued(AlertDigestMail::class, fn (AlertDigestMail $mail): bool => $mail->hasTo('siti@example.test') && $mail->queue === 'bulk');
        Mail::assertQueued(AlertDigestMail::class, function (AlertDigestMail $mail): bool {
            $mail->assertSeeInText('Senior Backend Developer');
            $mail->assertSeeInText('Backend jobs');

            return true;
        });

        // Closing and opening it again is not a new job.
        $this->api('POST', "/company/jobs/{$job}/close")->assertOk();
        $this->api('POST', "/company/jobs/{$job}/reopen", ['deadline' => '2026-12-31'])->assertOk();
        $this->assertCount(1, $this->alertNotifications());
        Mail::assertQueued(AlertDigestMail::class, 1);
    }

    public function test_an_approved_job_notifies_but_a_restored_or_reopened_one_does_not(): void
    {
        $this->alertId();
        $this->later();
        $job = $this->liveJobFor('Approved');

        Event::dispatch(new JobDecided($job, $this->companyId, JobDecided::RESTORED, 'taken_down', 'active', null, 1, null, null, now()->toDateTimeImmutable()));
        Event::dispatch(new JobStatusChanged($job, $this->companyId, JobStatusChanged::REOPENED, 'closed', 'active', $this->adminId, null, null, now()->toDateTimeImmutable()));
        Event::dispatch(new JobStatusChanged($job, $this->companyId, JobStatusChanged::PUBLISHED, 'draft', 'pending_review', $this->adminId, null, null, now()->toDateTimeImmutable()));
        $this->assertSame([], $this->alertNotifications());

        Event::dispatch(new JobDecided($job, $this->companyId, JobDecided::APPROVED, 'pending_review', 'active', null, 1, null, null, now()->toDateTimeImmutable()));
        $this->assertCount(1, $this->alertNotifications());

        // The same job going live again (a second approval after a review) is told once.
        Event::dispatch(new JobDecided($job, $this->companyId, JobDecided::APPROVED, 'pending_review', 'active', null, 1, null, null, now()->toDateTimeImmutable()));
        $this->assertCount(1, $this->alertNotifications());
    }

    public function test_only_jobs_that_first_went_live_after_the_alert_started_are_sent(): void
    {
        $this->liveJobFor('Already live');
        $alert = $this->alertId();
        $this->later();

        $this->goLive('Before', ['published_at' => '2026-10-05 08:00:00']);
        $this->assertSame([], $this->alertNotifications());

        $new = $this->goLive('After');
        $this->assertSame([['type' => 'job_alert.matches', 'data' => ['alert_id' => $alert, 'job_ids' => [$new], 'total' => 1]]], $this->alertNotifications());
    }

    public function test_the_alert_and_the_search_agree_on_what_is_live_and_what_matches(): void
    {
        $monthly = $this->referenceId('reference_salary_periods', 'monthly');
        $visible = ['salary_max' => 15000000, 'salary_period_id' => $monthly, 'salary_visible' => true];
        $this->alertId(['criteria' => ['role_id' => [$this->roleId], 'q' => 'developer', 'salary_min' => 12000000, 'salary_period_id' => $monthly]]);
        $this->later();
        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['status' => 'suspended']);

        $this->goLive('Developer wrong period', [...$visible, 'salary_period_id' => $this->referenceId('reference_salary_periods', 'yearly')]);
        $this->goLive('Developer hidden salary', [...$visible, 'salary_visible' => false]);
        $this->goLive('Developer too low', [...$visible, 'salary_max' => 11000000]);
        $this->goLive('Designer', $visible);
        $this->goLive('Developer closed', [...$visible, 'status' => 'closed']);
        $this->goLive('Developer late', [...$visible, 'deadline' => '2026-10-04']);
        $this->goLive('Developer suspended company', [...$visible, 'company_id' => $this->otherCompanyId]);
        $this->assertSame([], $this->alertNotifications());

        $good = $this->goLive('Backend developer', [...$visible, 'salary_min' => 10000000]);

        $this->assertSame([[$good]], array_column(array_column($this->alertNotifications(), 'data'), 'job_ids'));
    }

    public function test_the_email_of_an_immediate_alert_is_limited_to_five_a_day_but_the_notification_never_is(): void
    {
        Mail::fake();
        $this->alertId();
        $this->later('2026-10-05 10:00:00');

        foreach (range(1, 7) as $number) {
            $this->goLive("Job {$number}");
        }

        $this->assertCount(7, $this->alertNotifications());
        Mail::assertQueued(AlertDigestMail::class, 5);

        // The next day in Jakarta begins at 17:00 UTC.
        $this->later('2026-10-05 17:30:00');
        $this->goLive('Job 8');
        $this->assertCount(8, $this->alertNotifications());
        Mail::assertQueued(AlertDigestMail::class, 6);
    }

    public function test_every_alert_has_its_own_email_cap(): void
    {
        Mail::fake();
        $this->alertId();
        $this->alertId(['name' => 'Second']);
        $this->later();

        foreach (range(1, 6) as $number) {
            $this->goLive("Job {$number}");
        }

        $this->assertCount(12, $this->alertNotifications());
        Mail::assertQueued(AlertDigestMail::class, 10);
    }

    public function test_the_channels_of_the_alert_the_preference_and_the_verified_address_decide_what_is_sent(): void
    {
        Mail::fake();
        $this->alertId(['name' => 'Email only', 'in_app' => false]);
        $this->later();

        $this->goLive('One');
        $this->assertSame([], $this->alertNotifications());
        Mail::assertQueued(AlertDigestMail::class, 1);

        $this->api('PUT', '/me/notification-preferences/job_alert/email', ['enabled' => false], $this->seekerToken)->assertNoContent();
        $this->goLive('Two');
        Mail::assertQueued(AlertDigestMail::class, 1);

        $this->api('PUT', '/me/notification-preferences/job_alert/email', ['enabled' => true], $this->seekerToken)->assertNoContent();
        DB::table('identity_users')->where('id', $this->seekerId)->update(['email_verified_at' => null]);
        $this->goLive('Three');
        Mail::assertQueued(AlertDigestMail::class, 1);

        DB::table('identity_users')->where('id', $this->seekerId)->update(['email_verified_at' => now()]);
        $this->alertId(['name' => 'In-app only', 'email' => false]);
        $this->later('2026-10-05 09:10:00');
        $this->goLive('Four');
        Mail::assertQueued(AlertDigestMail::class, 2);
        $this->assertCount(1, $this->alertNotifications());
    }

    public function test_a_paused_alert_sends_nothing_and_a_resumed_one_starts_from_now(): void
    {
        $id = $this->alertId();
        $this->api('POST', "/me/job-alerts/{$id}/pause", token: $this->seekerToken)->assertOk();
        $this->later();
        $this->goLive('While paused');
        $this->assertSame([], $this->alertNotifications());

        $this->later('2026-10-05 09:30:00');
        $this->api('POST', "/me/job-alerts/{$id}/resume", token: $this->seekerToken)->assertOk();
        $this->goLive('Published before the resume', ['published_at' => '2026-10-05 09:20:00']);
        $this->assertSame([], $this->alertNotifications());

        $this->later('2026-10-05 09:40:00');
        $job = $this->goLive('After the resume');
        $this->assertSame([$job], $this->alertNotifications()[0]['data']['job_ids']);
    }

    public function test_the_accounts_that_cannot_be_told_are_skipped_and_the_others_still_are(): void
    {
        Mail::fake();
        $other = $this->seeker('other@example.test', name: 'Other Seeker');
        $this->alertId();
        $this->alertId(token: $this->tokenFor($other));
        $this->later();

        DB::table('identity_users')->where('id', $this->seekerId)->update(['status' => 'deactivated']);
        $this->goLive('One');

        $this->assertSame([], $this->alertNotifications());
        $this->assertCount(1, $this->alertNotifications($other));
        Mail::assertQueued(AlertDigestMail::class, 1);
        Mail::assertQueued(AlertDigestMail::class, fn (AlertDigestMail $mail): bool => $mail->hasTo('other@example.test'));
    }

    // ----- daily and weekly summaries -----

    public function test_a_daily_summary_lists_the_jobs_since_the_last_one_at_eight_in_the_morning_jakarta_time(): void
    {
        Mail::fake();
        $alert = $this->alertId(['frequency' => 'daily']);
        $this->later('2026-10-05 10:00:00');
        $first = $this->liveJobFor('First');
        $second = $this->liveJobFor('Second', ['published_at' => '2026-10-05 12:00:00']);
        $this->liveJobFor('Other role', ['role_id' => $this->referenceId('reference_job_roles', 'data-analyst')]);

        // 07:30 in Jakarta: the slot of this day has not come, and the alert was made after the slot of the day before.
        Carbon::setTestNow('2026-10-06 00:30:00');
        $this->assertSame('Job alert summaries sent: 0', $this->runDigests());
        $this->assertSame([], $this->alertNotifications());

        Carbon::setTestNow('2026-10-06 01:10:00');
        $this->assertSame('Job alert summaries sent: 1', $this->runDigests());
        $this->assertSame([['type' => 'job_alert.matches', 'data' => ['alert_id' => $alert, 'job_ids' => [$second, $first], 'total' => 2]]], $this->alertNotifications());
        Mail::assertQueued(AlertDigestMail::class, 1);
        Mail::assertQueued(AlertDigestMail::class, function (AlertDigestMail $mail): bool {
            $mail->assertSeeInText('2 in total');
            $mail->assertSeeInText('First');
            $mail->assertSeeInText('Second');

            return true;
        });
        $this->assertSame('2026-10-06 01:00:00', DB::table('jobalert_alerts')->find($alert)->last_run_at);

        // Running it again, or later the same day, sends nothing; the next day brings only the new jobs.
        $this->assertSame('Job alert summaries sent: 0', $this->runDigests());
        $third = $this->liveJobFor('Third', ['published_at' => '2026-10-06 03:00:00']);
        Carbon::setTestNow('2026-10-07 02:00:00');
        $this->assertSame('Job alert summaries sent: 1', $this->runDigests());
        $this->assertSame([$third], $this->alertNotifications()[1]['data']['job_ids']);

        // A day with no new job sends nothing but moves the cursor.
        Carbon::setTestNow('2026-10-08 02:00:00');
        $this->assertSame('Job alert summaries sent: 0', $this->runDigests());
        $this->assertSame('2026-10-08 01:00:00', DB::table('jobalert_alerts')->find($alert)->last_run_at);
        $this->assertCount(2, $this->alertNotifications());
        Mail::assertQueued(AlertDigestMail::class, 2);
    }

    public function test_a_weekly_summary_comes_on_monday(): void
    {
        $alert = $this->alertId(['frequency' => 'weekly']);
        $daily = $this->alertId(['name' => 'Daily', 'frequency' => 'daily']);
        $this->later('2026-10-06 03:00:00');
        $job = $this->liveJobFor('Tuesday job');

        // Wednesday morning: the daily summary is due, the weekly one is not.
        Carbon::setTestNow('2026-10-07 02:00:00');
        $this->assertSame('Job alert summaries sent: 1', $this->runDigests());
        $this->assertSame([$daily], array_column(array_column($this->alertNotifications(), 'data'), 'alert_id'));

        // Monday at 07:59 in Jakarta, and then at 08:00.
        Carbon::setTestNow('2026-10-11 23:59:00');
        $this->assertSame('Job alert summaries sent: 0', $this->runDigests());
        Carbon::setTestNow('2026-10-12 01:05:00');
        $this->assertSame('Job alert summaries sent: 1', $this->runDigests());

        $weekly = array_values(array_filter(array_column($this->alertNotifications(), 'data'), fn (array $data): bool => $data['alert_id'] === $alert));
        $this->assertSame([['alert_id' => $alert, 'job_ids' => [$job], 'total' => 1]], $weekly);
        $this->assertSame('2026-10-12 01:00:00', DB::table('jobalert_alerts')->find($alert)->last_run_at);
        $this->assertSame('Job alert summaries sent: 0', $this->runDigests());
    }

    public function test_a_summary_names_at_most_ten_jobs_and_counts_them_all(): void
    {
        $alert = $this->alertId(['frequency' => 'daily']);
        $this->later('2026-10-05 10:00:00');

        foreach (range(1, 12) as $number) {
            $this->liveJobFor("Job {$number}", ['published_at' => '2026-10-05 10:'.sprintf('%02d', $number).':00']);
        }

        Carbon::setTestNow('2026-10-06 02:00:00');
        $this->runDigests();

        $data = $this->alertNotifications()[0]['data'];
        $this->assertSame([$alert, 12, 10], [$data['alert_id'], $data['total'], count($data['job_ids'])]);
    }

    public function test_a_paused_alert_gets_no_summary_and_a_resumed_one_no_backlog(): void
    {
        $id = $this->alertId(['frequency' => 'daily']);
        $this->api('POST', "/me/job-alerts/{$id}/pause", token: $this->seekerToken)->assertOk();
        $this->later('2026-10-05 10:00:00');
        $this->liveJobFor('While paused');

        Carbon::setTestNow('2026-10-06 02:00:00');
        $this->assertSame('Job alert summaries sent: 0', $this->runDigests());

        $this->api('POST', "/me/job-alerts/{$id}/resume", token: $this->seekerToken)->assertOk();
        $this->liveJobFor('After the resume', ['published_at' => '2026-10-06 03:00:00']);
        Carbon::setTestNow('2026-10-07 02:00:00');
        $this->assertSame('Job alert summaries sent: 1', $this->runDigests());
        $this->assertCount(1, $this->alertNotifications());
        $this->assertSame(1, $this->alertNotifications()[0]['data']['total']);
    }

    public function test_the_summary_of_an_account_that_cannot_be_told_moves_on_without_sending(): void
    {
        Mail::fake();
        $alert = $this->alertId(['frequency' => 'daily']);
        $this->later('2026-10-05 10:00:00');
        $this->liveJobFor('One');
        DB::table('identity_users')->where('id', $this->seekerId)->update(['status' => 'deactivated']);

        Carbon::setTestNow('2026-10-06 02:00:00');
        $this->assertSame('Job alert summaries sent: 0', $this->runDigests());

        $this->assertSame([], $this->alertNotifications());
        Mail::assertNothingQueued();
        $this->assertSame('2026-10-06 01:00:00', DB::table('jobalert_alerts')->find($alert)->last_run_at);
    }

    public function test_the_command_runs_every_day_at_eight_in_jakarta(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'jobalert:send-digests'));

        $this->assertCount(1, $events);
        $zone = $events->first()->timezone;
        $this->assertSame(['0 8 * * *', 'Asia/Jakarta'], [$events->first()->expression, is_string($zone) ? $zone : $zone?->getName()]);
    }

    // ----- erasure -----

    public function test_anonymising_an_account_deletes_its_alerts_only(): void
    {
        $other = $this->seeker('other@example.test');
        $this->alertId();
        $this->alertId(['name' => 'Second']);
        $kept = $this->alertId(token: $this->tokenFor($other));
        $this->later();
        $this->goLive('One');
        $this->assertSame(3, DB::table('jobalert_deliveries')->count());

        DB::table('identity_users')->where('id', $this->seekerId)->update(['anonymized_at' => now()]);
        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        $this->assertSame([$kept], DB::table('jobalert_alerts')->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(1, DB::table('jobalert_deliveries')->count());

        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));
        $this->assertSame(1, DB::table('jobalert_alerts')->count());
    }
}
