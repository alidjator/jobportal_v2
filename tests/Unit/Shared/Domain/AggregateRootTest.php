<?php

namespace Tests\Unit\Shared\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\AggregateRoot;
use Src\Shared\Domain\DomainEvent;

final class AggregateRootTest extends TestCase
{
    public function test_recorded_events_are_returned_once_and_then_cleared(): void
    {
        $event = new class implements DomainEvent
        {
            public function occurredAt(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-03 10:00:00');
            }
        };

        $aggregate = new class($event) extends AggregateRoot
        {
            public function __construct(DomainEvent $event)
            {
                $this->record($event);
            }
        };

        $this->assertSame([$event], $aggregate->pullEvents());
        $this->assertSame([], $aggregate->pullEvents());
    }
}
