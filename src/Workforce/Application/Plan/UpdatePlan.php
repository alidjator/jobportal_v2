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

/** Replaces the lines of a draft or rejected plan; a rejected plan becomes a draft again. */
final readonly class UpdatePlan
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private PlanLineRules $rules,
        private PlanFloor $floor,
        private PlanViews $views,
        private PlanChangeRecorder $recorder,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<PlanLineInput>  $lines
     *
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `plan-not-editable`
     * @throws BusinessRuleViolation `invalid-plan-line` or `plan-below-requisitions`
     */
    public function execute(Actor $actor, PlanSide $side, int $planId, array $lines, ?string $ipAddress, ?string $userAgent): PlanView
    {
        $scope = $this->access->manage($actor, $side);

        $plan = $this->transactions->run(function () use ($scope, $planId, $lines): Plan {
            $plan = $this->plans->find($planId, true);

            if ($plan === null || ! $scope->covers($plan->legalEntityId)) {
                throw EntityNotFound::of('Manpower plan', $planId);
            }

            if (! $plan->status->isEditable()) {
                throw new StateConflict('Only a draft or a rejected plan can be changed.', 'plan-not-editable');
            }

            $built = $this->rules->build($plan->legalEntityId, $lines, $scope->canSeeCosts, $plan->lines);
            $this->floor->assertHolds($plan->legalEntityId, $plan->fiscalYear, $built);
            $this->plans->replaceLines($planId, $built);

            if ($plan->status === PlanStatus::Rejected) {
                $this->plans->save($plan->with(['status' => PlanStatus::Draft]));
            }

            return $this->plans->find($planId) ?? throw new \LogicException('The plan was just stored.');
        });

        $this->recorder->record($plan, 'updated', $actor->id, $ipAddress, $userAgent);

        return $this->views->of([$plan], $scope)[0];
    }
}
