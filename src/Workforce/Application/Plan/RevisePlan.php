<?php

namespace Src\Workforce\Application\Plan;

use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Domain\Plan;
use Src\Workforce\Domain\PlanRepository;
use Src\Workforce\Domain\PlanStatus;

/** Starts a new version of the plan in force: a draft with the same lines, which supersedes the old version once it is approved. */
final readonly class RevisePlan
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private PlanViews $views,
        private PlanChangeRecorder $recorder,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `plan-not-in-force` or `plan-revision-open`
     */
    public function execute(Actor $actor, PlanSide $side, int $planId, ?string $ipAddress, ?string $userAgent): PlanView
    {
        $scope = $this->access->manage($actor, $side);

        $revision = $this->transactions->run(function () use ($actor, $scope, $planId): Plan {
            $plan = $this->plans->find($planId, true);

            if ($plan === null || ! $scope->covers($plan->legalEntityId)) {
                throw EntityNotFound::of('Manpower plan', $planId);
            }

            if ($plan->status !== PlanStatus::Approved) {
                throw new StateConflict('Only the version in force can be revised.', 'plan-not-in-force');
            }

            $versions = $this->plans->versionsOf($plan->legalEntityId, $plan->fiscalYear);

            foreach ($versions as $version) {
                if ($version->status !== PlanStatus::Approved && $version->status !== PlanStatus::Superseded) {
                    throw new StateConflict('This fiscal year already has a version being worked on.', 'plan-revision-open');
                }
            }

            $latest = max(array_map(static fn (Plan $version): int => $version->version, $versions));

            return $this->plans->create(new Plan(null, $plan->legalEntityId, $plan->fiscalYear, $latest + 1, PlanStatus::Draft, (int) $actor->id, $plan->lines));
        });

        $this->recorder->record($revision, 'revised', $actor->id, $ipAddress, $userAgent);

        return $this->views->of([$revision], $scope)[0];
    }
}
