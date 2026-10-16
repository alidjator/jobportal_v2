<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\LegalEntityDirectory;
use Src\Workforce\Domain\Events\RequisitionChanged;
use Src\Workforce\Domain\Requisition;

/** Announces a change of a requisition, tagged with its tenant and group so both audit logs show it. */
final readonly class RequisitionChangeRecorder
{
    public function __construct(private EventDispatcher $events, private Clock $clock, private LegalEntityDirectory $entities) {}

    /** @param  array<string, mixed>  $detail */
    public function record(Requisition $requisition, string $change, ?int $actorId, ?string $ipAddress = null, ?string $userAgent = null, array $detail = []): void
    {
        $this->events->dispatch([new RequisitionChanged(
            (int) $requisition->id, $requisition->companyId, $this->entities->groupOf($requisition->legalEntityId) ?? 0, $change, $actorId, $ipAddress, $userAgent, $this->clock->now(), $detail,
        )]);
    }
}
