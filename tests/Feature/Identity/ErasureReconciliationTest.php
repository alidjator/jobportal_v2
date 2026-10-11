<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Job\Infrastructure\Listeners\EraseSavedJobsWhenUserAnonymized;
use Src\Shared\Application\Accounts\ErasureStep;
use Src\Shared\Application\Accounts\ErasureSteps;
use Tests\FeatureTestCase;

final class ErasureReconciliationTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_every_context_registers_its_erasure_step_under_a_unique_name(): void
    {
        $names = array_map(fn (ErasureStep $step): string => $step->name(), $this->app->make(ErasureSteps::class)->all());

        $this->assertCount(12, $names);
        $this->assertSame($names, array_values(array_unique($names)));
    }

    public function test_a_finished_erasure_leaves_one_receipt_per_step(): void
    {
        $user = $this->anonymizedUser('2026-10-05 08:00:00');

        Event::dispatch(new UserAnonymized($user->id, now()->toDateTimeImmutable()));

        $this->assertSame(12, DB::table('identity_erasure_receipts')->where('user_id', $user->id)->count());
    }

    public function test_the_sweep_runs_again_only_the_steps_without_a_receipt(): void
    {
        $user = $this->anonymizedUser('2026-10-04 08:00:00');
        $ran = $this->steps(['alpha' => null, 'beta' => null]);
        DB::table('identity_erasure_receipts')->insert(['user_id' => $user->id, 'step' => 'alpha', 'completed_at' => now()]);

        $this->assertSame(0, Artisan::call('identity:reconcile-erasure'));

        $this->assertSame(['beta:'.$user->id], $ran->calls);
        $this->assertSame(['alpha', 'beta'], DB::table('identity_erasure_receipts')->where('user_id', $user->id)->orderBy('step')->pluck('step')->all());

        $ran->calls = [];
        Artisan::call('identity:reconcile-erasure');
        $this->assertSame([], $ran->calls);
    }

    public function test_a_step_that_fails_again_is_logged_and_the_others_still_run(): void
    {
        $user = $this->anonymizedUser('2026-10-04 08:00:00');
        $ran = $this->steps(['alpha' => new RuntimeException('disk full'), 'beta' => null]);
        $logger = \Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->withArgs(fn (string $message, array $context) => $context['step'] === 'alpha' && $context['user_id'] === $user->id);
        $this->app->instance(LoggerInterface::class, $logger);

        $this->assertSame(1, Artisan::call('identity:reconcile-erasure'));

        $this->assertSame(['beta'], DB::table('identity_erasure_receipts')->where('user_id', $user->id)->pluck('step')->all());
    }

    public function test_the_sweep_skips_recent_old_and_active_accounts(): void
    {
        $this->anonymizedUser('2026-10-05 08:30:00');
        $this->anonymizedUser('2026-06-01 08:00:00', 'old@example.test');
        UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Active', 'email' => 'active@example.test', 'status' => 'active', 'locale' => 'en']);
        $ran = $this->steps(['alpha' => null]);

        Artisan::call('identity:reconcile-erasure');

        $this->assertSame([], $ran->calls);
    }

    public function test_the_listener_logs_when_the_last_retry_fails(): void
    {
        Log::spy();
        $listener = $this->app->make(EraseSavedJobsWhenUserAnonymized::class);

        $listener->failed(new UserAnonymized(7, now()->toDateTimeImmutable()), new RuntimeException('boom'));

        Log::shouldHaveReceived('error')->once();
    }

    private function anonymizedUser(string $anonymizedAt, string $email = 'gone@example.test'): UserModel
    {
        return UserModel::query()->create([
            'type' => 'job_seeker', 'name' => 'Deleted user', 'email' => $email, 'status' => 'deactivated', 'locale' => 'en', 'anonymized_at' => $anonymizedAt,
        ]);
    }

    /**
     * Replaces the registered steps with recording stubs; a Throwable value makes that step fail.
     *
     * @param  array<string, \Throwable|null>  $behaviour
     */
    private function steps(array $behaviour): object
    {
        $recorder = new class
        {
            /** @var list<string> */
            public array $calls = [];
        };

        $steps = [];

        foreach ($behaviour as $name => $failure) {
            $steps[] = new class($name, $failure, $recorder) implements ErasureStep
            {
                public function __construct(private string $name, private ?\Throwable $failure, private object $recorder) {}

                public function name(): string
                {
                    return $this->name;
                }

                public function execute(int $userId): void
                {
                    if ($this->failure !== null) {
                        throw $this->failure;
                    }

                    $this->recorder->calls[] = $this->name.':'.$userId;
                }
            };
        }

        $this->app->instance(ErasureSteps::class, new class($steps) implements ErasureSteps
        {
            public function __construct(private array $steps) {}

            public function all(): array
            {
                return $this->steps;
            }
        });

        return $recorder;
    }
}
