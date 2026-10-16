<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Domain\Plan;
use Src\Workforce\Domain\PlanRepository;
use Src\Workforce\Domain\PlanStatus;

/**
 * Starts the plan of a legal entity for a fiscal year, as version 1 and a draft. A tenant plans its own legal entity; a group member names the entity. A year that already has a plan
 * is changed through its open version, or revised once a version is in force.
 */
final readonly class CreatePlan
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private PlanLineRules $rules,
        private PlanViews $views,
        private PlanChangeRecorder $recorder,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<PlanLineInput>  $lines
     *
     * @throws AccessDenied
     * @throws EntityNotFound the legal entity is not one of the group
     * @throws StateConflict `plan-exists`
     * @throws BusinessRuleViolation `invalid-plan-line`
     */
    public function execute(Actor $actor, PlanSide $side, ?int $legalEntityId, int $fiscalYear, array $lines, ?string $ipAddress, ?string $userAgent): PlanView
    {
        $scope = $this->access->manage($actor, $side);
        $entityId = $side === PlanSide::Company ? $scope->legalEntityIds[0] : ($legalEntityId ?? throw new BusinessRuleViolation('Name the legal entity of the plan.', 'invalid-plan-line'));

        if (! $scope->covers($entityId)) {
            throw EntityNotFound::of('Legal entity', $entityId);
        }

        $plan = $this->transactions->run(function () use ($actor, $scope, $entityId, $fiscalYear, $lines): Plan {
            if ($this->plans->versionsOf($entityId, $fiscalYear) !== []) {
                throw new StateConflict('This legal entity already has a plan for this fiscal year: change its open version or revise the one in force.', 'plan-exists');
            }

            return $this->plans->create(new Plan(null, $entityId, $fiscalYear, 1, PlanStatus::Draft, (int) $actor->id, $this->rules->build($entityId, $lines, $scope->canSeeCosts, [])));
        });

        $this->recorder->record($plan, 'created', $actor->id, $ipAddress, $userAgent);

        return $this->views->of([$plan], $scope)[0];
    }
}
