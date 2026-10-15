<?php

namespace Tests\Unit\Shared\Queue;

use ReflectionClass;
use Src\JobAlert\Infrastructure\Listeners\NotifyAlertsOfLiveJob;
use Src\Matching\Infrastructure\Jobs\SendRecommendationBatchJob;
use Src\Newsletter\Infrastructure\Jobs\DispatchNewsletterIssueJob;
use Src\Shared\Infrastructure\Queue\QueueName;
use Tests\TestCase;

/** A job that runs longer than `retry_after` is handed to a second worker while it is still running, so every long job stops itself before that. */
final class QueueTimeoutTest extends TestCase
{
    /** @return list<class-string> */
    private function erasureListeners(): array
    {
        $classes = [];

        foreach (glob(base_path('src/*/Infrastructure/Listeners/{Erase,Purge}*WhenUserAnonymized.php'), GLOB_BRACE) ?: [] as $file) {
            $context = basename(dirname($file, 3));
            $classes[] = "Src\\{$context}\\Infrastructure\\Listeners\\".basename($file, '.php');
        }

        return $classes;
    }

    private function timeoutOf(string $class): int
    {
        $property = (new ReflectionClass($class))->getProperty('timeout');

        return (int) $property->getDefaultValue();
    }

    public function test_bulk_has_its_own_connection_with_a_longer_retry_after(): void
    {
        $bulk = config('queue.connections.bulk');

        $this->assertSame('bulk', $bulk['queue']);
        $this->assertSame(600, $bulk['retry_after']);
        $this->assertSame(90, config('queue.connections.database.retry_after'));
    }

    public function test_the_long_bulk_jobs_stop_before_the_bulk_retry_after(): void
    {
        foreach ([NotifyAlertsOfLiveJob::class, DispatchNewsletterIssueJob::class, SendRecommendationBatchJob::class] as $class) {
            $this->assertSame(540, $this->timeoutOf($class), $class);
        }

        $this->assertLessThan(config('queue.connections.bulk.retry_after'), 540);
    }

    public function test_every_erasure_listener_stops_before_the_default_retry_after(): void
    {
        $listeners = $this->erasureListeners();

        $this->assertCount(14, $listeners, 'A new erasure listener must be added to the expected count and set a timeout.');

        foreach ($listeners as $class) {
            $this->assertSame(60, $this->timeoutOf($class), $class);
            $this->assertLessThan(config('queue.connections.database.retry_after'), $this->timeoutOf($class), $class);
        }
    }

    public function test_bulk_jobs_use_the_bulk_connection_unless_they_run_in_the_request(): void
    {
        $this->assertNull(QueueName::Bulk->connection(), 'The test environment runs jobs synchronously.');
        $this->assertNull(QueueName::Default->connection());
        $this->assertNull(QueueName::Critical->connection());

        config(['queue.connections.bulk.driver' => 'database']);

        $this->assertSame('bulk', QueueName::Bulk->connection());
        $this->assertNull(QueueName::Default->connection());
    }
}
