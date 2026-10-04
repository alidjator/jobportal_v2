<?php

namespace Tests\Feature\Shared;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\DomainEvent;
use Src\Shared\Infrastructure\Events\LaravelEventDispatcher;
use Src\Shared\Infrastructure\Persistence\LaravelTransactionManager;
use Tests\FeatureTestCase;

final class SharedBindingsTest extends FeatureTestCase
{
    public function test_the_interfaces_resolve_to_the_laravel_implementations(): void
    {
        $this->assertInstanceOf(LaravelTransactionManager::class, $this->app->make(TransactionManager::class));
        $this->assertInstanceOf(LaravelEventDispatcher::class, $this->app->make(EventDispatcher::class));
    }

    public function test_the_transaction_manager_returns_the_result_and_runs_inside_a_transaction(): void
    {
        $outer = DB::connection()->transactionLevel();

        $level = $this->app->make(TransactionManager::class)->run(fn () => DB::connection()->transactionLevel());

        $this->assertSame($outer + 1, $level);
    }

    public function test_a_failing_callback_rolls_its_changes_back_and_rethrows(): void
    {
        $manager = $this->app->make(TransactionManager::class);

        try {
            $manager->run(function (): void {
                DB::table('reference_job_categories')->insert(['name' => '{}', 'slug' => 'rollback-probe']);

                throw new RuntimeException('boom');
            });
            $this->fail('The exception must propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertFalse(DB::table('reference_job_categories')->where('slug', 'rollback-probe')->exists());
    }

    public function test_the_event_dispatcher_publishes_each_event_to_laravel_listeners(): void
    {
        Event::fake();

        $event = new class implements DomainEvent
        {
            public function occurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable;
            }
        };

        $this->app->make(EventDispatcher::class)->dispatch([$event]);

        Event::assertDispatched($event::class);
    }
}
