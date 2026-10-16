<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\Organization\LegalEntityDirectory;
use Src\Shared\Application\Organization\PositionDirectory;
use Src\Workforce\Domain\Plan;

/** Builds the views of plans for a reader: names, fiscal year days and position titles are looked up once per legal entity. */
final readonly class PlanViews
{
    public function __construct(private LegalEntityDirectory $entities, private PositionDirectory $positions, private TenantRegistry $tenants, private FiscalCalendar $calendar, private Clock $clock) {}

    /**
     * @param  list<Plan>  $plans
     * @return list<PlanView>
     */
    public function of(array $plans, PlanScope $scope): array
    {
        $entityIds = array_values(array_unique(array_map(static fn (Plan $plan): int => $plan->legalEntityId, $plans)));
        $names = $entityIds === [] ? [] : $this->entities->namesOfGroup($scope->groupId);
        $tenants = $this->tenants->tenantsOf($entityIds);
        $positions = [];

        foreach ($entityIds as $entityId) {
            $positions[$entityId] = $this->positions->ofEntity($entityId, $this->clock->now());
        }

        return array_map(function (Plan $plan) use ($scope, $names, $tenants, $positions): PlanView {
            [$startsOn, $endsOn] = $this->calendar->period($plan->fiscalYear, $tenants[$plan->legalEntityId] ?? null);

            return new PlanView($plan, $names[$plan->legalEntityId] ?? '', $startsOn, $endsOn, $positions[$plan->legalEntityId] ?? [], $scope->canSeeCosts);
        }, $plans);
    }
}
