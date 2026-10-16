<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\LegalEntityDirectory;
use Src\Workforce\Domain\Events\PlanChanged;
use Src\Workforce\Domain\Plan;

/** Announces a change of a plan, tagged with the tenant of its legal entity and the group, so the audit log of both shows it. */
final readonly class PlanChangeRecorder
{
    public function __construct(private EventDispatcher $events, private Clock $clock, private TenantRegistry $tenants, private LegalEntityDirectory $entities) {}

    public function record(Plan $plan, string $change, ?int $actorId, ?string $ipAddress = null, ?string $userAgent = null, ?string $reason = null): void
    {
        $this->events->dispatch([new PlanChanged(
            (int) $plan->id, $plan->legalEntityId, $this->entities->groupOf($plan->legalEntityId) ?? 0, $this->tenants->tenantsOf([$plan->legalEntityId])[$plan->legalEntityId] ?? null,
            $plan->fiscalYear, $plan->version, $change, $actorId, $ipAddress, $userAgent, $this->clock->now(), $reason,
        )]);
    }
}
