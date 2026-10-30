<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Platform\Application\PlatformSettings;
use Src\Platform\Application\Queue\QueueHealth;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-platform-admin.md` section 9: the queue watch, emails on new failed jobs and on a stalled queue. */
final class QueueWatchTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->app->make(SettingsStore::class)->setGlobal(PlatformSettings::OPERATIONS_EMAIL, 'ops@example.test', null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function failed(string $queue = 'default', string $class = 'App\\Jobs\\SendMail', string $exception = "RuntimeException: Connection refused in /app/Mail.php:10\nStack trace:\n#0 secret"): int
    {
        return (int) DB::table('failed_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => $queue,
            'payload' => json_encode(['displayName' => $class, 'data' => ['email' => 'private@example.test']]),
            'exception' => $exception,
            'failed_at' => now(),
        ]);
    }

    private function waiting(string $queue, int $secondsAgo, ?int $reservedAt = null): void
    {
        DB::table('jobs')->insert(['queue' => $queue, 'payload' => '{}', 'attempts' => 0, 'reserved_at' => $reservedAt, 'available_at' => now()->timestamp - $secondsAgo, 'created_at' => now()->timestamp - $secondsAgo]);
    }

    /** @return list<string> the text of every message sent so far */
    private function bodies(): array
    {
        return array_map(
            fn ($message): string => $message->getOriginalMessage()->getSubject()."\n".$message->getOriginalMessage()->getTextBody(),
            $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all(),
        );
    }

    // ----- failed jobs -----

    public function test_new_failed_jobs_are_reported_with_counts_and_the_first_line_of_the_error_but_no_payload(): void
    {
        $this->failed();
        $this->failed(class: 'App\\Jobs\\SendMail');
        $this->failed('bulk', 'App\\Jobs\\Digest', 'Boom');

        $this->artisan('platform:check-scheduled-tasks')->expectsOutputToContain('Queue alerts: failed 2, stalled 0, recovered 0')->assertSuccessful();

        $bodies = $this->bodies();
        $this->assertCount(1, $bodies);
        $this->assertStringContainsString('default: 2 failed (App\\Jobs\\SendMail (2))', $bodies[0]);
        $this->assertStringContainsString('Newest: RuntimeException: Connection refused in /app/Mail.php:10', $bodies[0]);
        $this->assertStringContainsString('bulk: 1 failed (App\\Jobs\\Digest (1))', $bodies[0]);
        $this->assertStringNotContainsString('private@example.test', $bodies[0]);
        $this->assertStringNotContainsString('Stack trace', $bodies[0]);
        $this->assertSame(2, DB::table('platform_queue_alerts')->where('kind', 'failed')->count());
    }

    public function test_a_queue_is_reported_at_most_once_an_hour_and_only_for_jobs_newer_than_the_last_email(): void
    {
        $first = $this->failed();
        $this->artisan('platform:check-scheduled-tasks')->assertSuccessful();
        $this->assertCount(1, $this->bodies());
        $this->assertSame($first, (int) DB::table('platform_queue_alerts')->where('kind', 'failed')->value('last_failed_job_id'));

        $second = $this->failed();
        Carbon::setTestNow('2026-10-05 09:59:00');
        $this->artisan('platform:check-scheduled-tasks')->assertSuccessful();
        $this->assertCount(1, $this->bodies(), 'Within the hour nothing more is sent.');

        Carbon::setTestNow('2026-10-05 10:01:00');
        $this->artisan('platform:check-scheduled-tasks')->assertSuccessful();
        $this->assertCount(2, $this->bodies());
        $this->assertStringContainsString('default: 1 failed', $this->bodies()[1]);
        $this->assertSame($second, (int) DB::table('platform_queue_alerts')->where('kind', 'failed')->value('last_failed_job_id'));

        Carbon::setTestNow('2026-10-05 12:00:00');
        $this->artisan('platform:check-scheduled-tasks')->assertSuccessful();
        $this->assertCount(2, $this->bodies(), 'No new failure, no email.');
    }

    // ----- stalled queue -----

    public function test_a_queue_stalls_after_ten_minutes_and_is_reported_once_while_it_lasts_and_once_when_it_recovers(): void
    {
        $this->waiting('bulk', 601);
        $this->waiting('default', 599);
        $this->waiting('critical', 3600, reservedAt: now()->timestamp);

        $this->artisan('platform:check-scheduled-tasks')->expectsOutputToContain('Queue alerts: failed 0, stalled 1, recovered 0')->assertSuccessful();
        $this->assertCount(1, $this->bodies());
        $this->assertStringContainsString('bulk: oldest job waits 10 minutes (1 waiting)', $this->bodies()[0]);
        $this->assertStringNotContainsString('default:', $this->bodies()[0]);

        $this->artisan('platform:check-scheduled-tasks')->expectsOutputToContain('stalled 0, recovered 0')->assertSuccessful();
        $this->assertCount(1, $this->bodies());

        DB::table('jobs')->where('queue', 'bulk')->delete();
        $this->artisan('platform:check-scheduled-tasks')->expectsOutputToContain('stalled 0, recovered 1')->assertSuccessful();
        $this->assertCount(2, $this->bodies());
        $this->assertStringContainsString('bulk', $this->bodies()[1]);

        $this->artisan('platform:check-scheduled-tasks')->expectsOutputToContain('stalled 0, recovered 0')->assertSuccessful();
        $this->assertCount(2, $this->bodies());

        $this->waiting('bulk', 900);
        $this->artisan('platform:check-scheduled-tasks')->expectsOutputToContain('stalled 1, recovered 0')->assertSuccessful();
        $this->assertCount(3, $this->bodies());
    }

    public function test_a_job_that_is_not_due_yet_does_not_stall_a_queue(): void
    {
        $this->waiting('bulk', -3600);

        $this->artisan('platform:check-scheduled-tasks')->expectsOutputToContain('stalled 0')->assertSuccessful();

        $this->assertSame([], $this->bodies());
    }

    // ----- without an address -----

    public function test_nothing_is_sent_and_nothing_is_marked_without_an_operations_address(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(PlatformSettings::OPERATIONS_EMAIL, '', null);
        $this->failed();
        $this->waiting('bulk', 900);

        $this->artisan('platform:check-scheduled-tasks')->assertSuccessful();

        $this->assertSame([], $this->bodies());
        $this->assertSame(0, DB::table('platform_queue_alerts')->count());

        $this->app->make(SettingsStore::class)->setGlobal(PlatformSettings::OPERATIONS_EMAIL, 'ops@example.test', null);
        $this->artisan('platform:check-scheduled-tasks')->assertSuccessful();

        $this->assertCount(2, $this->bodies());
    }

    // ----- the port -----

    public function test_the_queue_health_counts_waiting_oldest_wait_and_failures_of_the_last_day(): void
    {
        $this->waiting('bulk', 120);
        $this->waiting('bulk', 30);
        $this->failed('default');
        DB::table('failed_jobs')->where('queue', 'default')->update(['failed_at' => now()->subDays(2)]);
        $this->failed('critical');

        $queues = collect($this->app->make(QueueHealth::class)->queues(now()->toDateTimeImmutable()))->keyBy('queue');

        $this->assertSame(['bulk', 'critical'], $queues->keys()->all());
        $this->assertSame([2, 120, 0], [$queues['bulk']->waiting, $queues['bulk']->oldestWaitSeconds, $queues['bulk']->failedLast24Hours]);
        $this->assertSame([0, null, 1], [$queues['critical']->waiting, $queues['critical']->oldestWaitSeconds, $queues['critical']->failedLast24Hours]);
        $this->assertSame(['critical', 'default'], $this->app->make(QueueHealth::class)->failedQueues());
    }
}
