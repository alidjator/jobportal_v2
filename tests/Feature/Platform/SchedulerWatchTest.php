<?php

namespace Tests\Feature\Platform;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Platform\Application\PlatformSettings;
use Src\Platform\Application\Scheduler\ReadScheduler;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Platform\Infrastructure\Scheduler\ScheduledTaskName;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-platform-admin.md` section 8: what the platform remembers about scheduled tasks, the alerts and the health check. */
final class SchedulerWatchTest extends FeatureTestCase
{
    private const string HEARTBEAT = 'platform:record-scheduler-tick';

    private const string DAILY = 'platform:purge-audit-logs';

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function event(string $name): Event
    {
        foreach ($this->app->make(Schedule::class)->events() as $event) {
            if (ScheduledTaskName::of($event) === $name) {
                return $event;
            }
        }

        $this->fail("The task {$name} is not scheduled.");
    }

    /** @param  array<string, mixed>  $values */
    private function row(string $task, string $expression, array $values = []): void
    {
        DB::table('platform_scheduled_task_runs')->insert(['task' => $task, 'expression' => $expression, 'consecutive_failures' => 0, ...$values]);
    }

    /** @return list<string> */
    private function sentTo(): array
    {
        return array_map(
            fn ($message): string => $message->getOriginalMessage()->getTo()[0]->getAddress(),
            $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all(),
        );
    }

    private function health(): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders()->getJson('/api/v1/health/scheduler');
    }

    public function test_every_scheduled_task_has_a_distinct_stable_name(): void
    {
        $names = array_map(ScheduledTaskName::of(...), $this->app->make(Schedule::class)->events());

        $this->assertSame($names, array_values(array_unique($names)), 'Two scheduled tasks share a name.');
        $this->assertSame([], array_values(array_filter($names, fn (string $name): bool => str_starts_with($name, 'unnamed-'))), 'A scheduled task has no artisan command or name().');
        $this->assertContains(self::HEARTBEAT, $names);
        $this->assertContains('platform:check-scheduled-tasks', $names);
        $this->assertContains('sanctum:prune-expired', $names);
    }

    public function test_the_scheduler_events_are_recorded(): void
    {
        $event = $this->event(self::DAILY);

        Events::dispatch(new ScheduledTaskStarting($event));
        $event->exitCode = 0;
        Events::dispatch(new ScheduledTaskFinished($event, 0.2));

        $row = DB::table('platform_scheduled_task_runs')->where('task', self::DAILY)->first();
        $this->assertNotNull($row->last_started_at);
        $this->assertNotNull($row->last_finished_at);
        $this->assertSame(0, (int) $row->consecutive_failures);
        $this->assertSame($event->expression, $row->expression);
    }

    public function test_a_failure_is_counted_and_a_finish_clears_it(): void
    {
        $event = $this->event(self::DAILY);

        Events::dispatch(new ScheduledTaskFailed($event, new RuntimeException('The disk is full')));
        Events::dispatch(new ScheduledTaskFailed($event, new RuntimeException('The disk is full')));

        $row = DB::table('platform_scheduled_task_runs')->where('task', self::DAILY)->first();
        $this->assertSame(2, (int) $row->consecutive_failures);
        $this->assertSame('RuntimeException: The disk is full', $row->last_failure);
        $this->assertNotNull($row->last_failed_at);

        $event->exitCode = 0;
        Events::dispatch(new ScheduledTaskFinished($event, 0.1));
        $this->assertSame(0, (int) DB::table('platform_scheduled_task_runs')->where('task', self::DAILY)->value('consecutive_failures'));
    }

    public function test_a_command_that_exits_with_a_failure_code_counts_once_not_as_a_finish(): void
    {
        $event = $this->event(self::DAILY);
        $event->exitCode = 1;

        Events::dispatch(new ScheduledTaskFinished($event, 0.1));
        Events::dispatch(new ScheduledTaskFailed($event, new RuntimeException('Scheduled command failed with exit code [1].')));

        $row = DB::table('platform_scheduled_task_runs')->where('task', self::DAILY)->first();
        $this->assertNull($row->last_finished_at);
        $this->assertSame(1, (int) $row->consecutive_failures);
    }

    public function test_a_task_is_late_after_two_intervals_and_five_minutes(): void
    {
        $this->row(self::HEARTBEAT, '* * * * *', ['last_finished_at' => now()->subMinutes(6)]);
        $this->row(self::DAILY, '0 3 * * *', ['last_finished_at' => now()->subHours(40)]);
        $this->row('platform:check-scheduled-tasks', '*/5 * * * *', ['last_finished_at' => now()->subMinutes(14)]);

        $tasks = collect($this->app->make(ReadScheduler::class)->execute()->tasks)->keyBy('task');

        $this->assertFalse($tasks[self::HEARTBEAT]->late, '6 minutes is within 2 minutes + 5 minutes');
        $this->assertFalse($tasks[self::DAILY]->late, '40 hours is within 2 days + 5 minutes');
        $this->assertFalse($tasks['platform:check-scheduled-tasks']->late, '14 minutes is within 10 minutes + 5 minutes');

        DB::table('platform_scheduled_task_runs')->where('task', self::HEARTBEAT)->update(['last_finished_at' => now()->subMinutes(8)]);
        DB::table('platform_scheduled_task_runs')->where('task', self::DAILY)->update(['last_finished_at' => now()->subHours(49)]);
        DB::table('platform_scheduled_task_runs')->where('task', 'platform:check-scheduled-tasks')->update(['last_finished_at' => now()->subMinutes(16)]);

        $tasks = collect($this->app->make(ReadScheduler::class)->execute()->tasks)->keyBy('task');

        $this->assertTrue($tasks[self::HEARTBEAT]->late);
        $this->assertTrue($tasks[self::DAILY]->late);
        $this->assertTrue($tasks['platform:check-scheduled-tasks']->late);
    }

    public function test_a_task_that_never_finished_is_late_counted_from_its_first_start(): void
    {
        $this->row(self::HEARTBEAT, '* * * * *', ['last_started_at' => now()->subMinutes(30)]);

        $this->assertTrue($this->app->make(ReadScheduler::class)->execute()->tasks[0]->late);
    }

    public function test_tasks_that_left_the_schedule_are_ignored_and_the_unhealthy_come_first(): void
    {
        $this->row('platform:gone', '* * * * *', ['last_finished_at' => now()->subDays(3)]);
        $this->row('a:healthy', '* * * * *', ['last_finished_at' => now()]);
        $this->row(self::DAILY, '0 3 * * *', ['last_finished_at' => now(), 'consecutive_failures' => 1, 'last_failure' => 'boom']);
        $this->row('sanctum:prune-expired', '30 2 * * *', ['last_finished_at' => now()]);

        $tasks = $this->app->make(ReadScheduler::class)->execute()->tasks;

        $this->assertSame([self::DAILY, 'sanctum:prune-expired'], array_map(fn ($task): string => $task->task, $tasks));
        $this->assertTrue($tasks[0]->failing);
        $this->assertFalse($tasks[1]->failing);
    }

    public function test_the_health_check_answers_200_while_the_heartbeat_is_recent_and_503_otherwise(): void
    {
        $this->health()->assertStatus(503)->assertJsonPath('status', 'down');

        $this->row(self::HEARTBEAT, '* * * * *', ['last_finished_at' => now()->subMinutes(4)]);
        $this->health()->assertOk()->assertJsonPath('status', 'ok');

        DB::table('platform_scheduled_task_runs')->update(['last_finished_at' => now()->subMinutes(6)]);
        $this->health()->assertStatus(503);
    }

    public function test_the_health_check_reveals_no_other_data(): void
    {
        $this->row(self::HEARTBEAT, '* * * * *', ['last_finished_at' => now()]);
        $this->row(self::DAILY, '0 3 * * *', ['consecutive_failures' => 3, 'last_failure' => 'secret detail']);

        $this->assertSame(['status' => 'ok'], $this->health()->assertOk()->json());
    }

    public function test_the_operations_card_lists_the_scheduler_with_the_unhealthy_first(): void
    {
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo('dashboard.view');
        $token = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;

        $finished = now()->subMinute();
        $this->row(self::HEARTBEAT, '* * * * *', ['last_finished_at' => $finished]);
        $this->row(self::DAILY, '0 3 * * *', ['last_finished_at' => now()->subHour(), 'consecutive_failures' => 2]);

        $this->app['auth']->forgetGuards();
        $scheduler = $this->withToken($token)->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.cards.operations.scheduler');

        $this->assertSame($finished->toIso8601String(), Carbon::parse($scheduler['last_tick_at'])->toIso8601String());
        $this->assertSame([self::DAILY, self::HEARTBEAT], array_column($scheduler['tasks'], 'task'));
        $this->assertSame([false, false], array_column($scheduler['tasks'], 'late'));
        $this->assertSame([true, false], array_column($scheduler['tasks'], 'failing'));
        $this->assertSame(['task', 'last_finished_at', 'late', 'failing'], array_keys($scheduler['tasks'][0]));
    }

    public function test_an_alert_is_sent_once_when_a_task_turns_failing_and_once_when_it_recovers(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(PlatformSettings::OPERATIONS_EMAIL, 'ops@example.test', null);
        $this->row(self::DAILY, '0 3 * * *', ['last_finished_at' => now()->subHour(), 'consecutive_failures' => 1, 'last_failure' => 'RuntimeException: The disk is full']);
        $this->row(self::HEARTBEAT, '* * * * *', ['last_finished_at' => now()->subMinutes(20)]);

        $this->artisan('platform:check-scheduled-tasks')->expectsOutputToContain('Alerts raised: 2, recovered: 0')->assertSuccessful();
        $this->assertSame(['ops@example.test'], $this->sentTo());
        $message = $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()->all()[0]->getOriginalMessage();
        $this->assertStringContainsString(self::DAILY, $message->getTextBody());
        $this->assertStringContainsString('The disk is full', $message->getTextBody());
        $this->assertStringContainsString(self::HEARTBEAT, $message->getTextBody());
        $this->assertSame(2, DB::table('platform_scheduled_task_runs')->whereNotNull('alerted_at')->count());

        $this->artisan('platform:check-scheduled-tasks')->expectsOutputToContain('Alerts raised: 0, recovered: 0')->assertSuccessful();
        $this->assertCount(1, $this->sentTo());

        DB::table('platform_scheduled_task_runs')->where('task', self::DAILY)->update(['consecutive_failures' => 0]);
        $this->artisan('platform:check-scheduled-tasks')->expectsOutputToContain('Alerts raised: 0, recovered: 1')->assertSuccessful();
        $this->assertCount(2, $this->sentTo());
        $this->assertNull(DB::table('platform_scheduled_task_runs')->where('task', self::DAILY)->value('alerted_at'));
        $this->assertNotNull(DB::table('platform_scheduled_task_runs')->where('task', self::HEARTBEAT)->value('alerted_at'));
    }

    public function test_nothing_is_sent_and_no_alert_is_marked_without_an_operations_address(): void
    {
        $this->row(self::DAILY, '0 3 * * *', ['last_finished_at' => now(), 'consecutive_failures' => 1]);

        $this->artisan('platform:check-scheduled-tasks')->assertSuccessful();

        $this->assertSame([], $this->sentTo());
        $this->assertNull(DB::table('platform_scheduled_task_runs')->where('task', self::DAILY)->value('alerted_at'));

        $this->app->make(SettingsStore::class)->setGlobal(PlatformSettings::OPERATIONS_EMAIL, 'ops@example.test', null);
        $this->artisan('platform:check-scheduled-tasks')->assertSuccessful();

        $this->assertSame(['ops@example.test'], $this->sentTo());
    }

    public function test_a_suppressed_operations_address_still_gets_the_alert(): void
    {
        DB::table('platform_email_suppressions')->insert(['email' => 'ops@example.test', 'reason' => 'hard_bounce', 'source' => 'test', 'created_at' => now()]);
        $this->app->make(SettingsStore::class)->setGlobal(PlatformSettings::OPERATIONS_EMAIL, 'ops@example.test', null);
        $this->row(self::DAILY, '0 3 * * *', ['last_finished_at' => now(), 'consecutive_failures' => 1]);

        $this->artisan('platform:check-scheduled-tasks')->assertSuccessful();

        $this->assertSame(['ops@example.test'], $this->sentTo());
    }

    public function test_rows_of_tasks_that_left_the_schedule_are_forgotten_by_the_check(): void
    {
        $this->row('platform:gone', '* * * * *');
        $this->row(self::DAILY, '0 3 * * *', ['last_finished_at' => now()]);

        $this->artisan('platform:check-scheduled-tasks')->assertSuccessful();

        $this->assertSame([self::DAILY], DB::table('platform_scheduled_task_runs')->pluck('task')->all());
    }
}
