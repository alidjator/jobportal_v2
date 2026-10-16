<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Workforce\Domain\PlanRepository;

final readonly class GetPlan
{
    public function __construct(private PlanAccess $access, private PlanRepository $plans, private PlanViews $views) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound the plan does not exist or belongs to a legal entity the actor cannot reach
     */
    public function execute(Actor $actor, PlanSide $side, int $planId): PlanView
    {
        $scope = $this->access->read($actor, $side);
        $plan = $this->plans->find($planId);

        if ($plan === null || ! $scope->covers($plan->legalEntityId)) {
            throw EntityNotFound::of('Manpower plan', $planId);
        }

        return $this->views->of([$plan], $scope)[0];
    }
}
