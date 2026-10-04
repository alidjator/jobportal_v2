<?php

namespace Src\Shared\Domain;

/** Base of aggregates that record domain events. The use case dispatches them after the transaction commits. */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    protected function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Returns the recorded events and clears them.
     *
     * @return list<DomainEvent>
     */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
