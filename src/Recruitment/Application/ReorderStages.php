<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageOrder;
use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A company admin replaces the whole order of the stages. The list must name every stage once and obey the order rules. */
final readonly class ReorderStages
{
    public function __construct(private RecruitmentAccess $access, private StageRepository $stages, private TransactionManager $transactions) {}

    /**
     * @param  list<int>  $stageIds
     * @return list<Stage> the stages in their new order
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     * @throws BusinessRuleViolation `invalid-stage-order`
     */
    public function execute(Actor $actor, array $stageIds): array
    {
        $member = $this->access->companyAdmin($actor);

        $this->transactions->run(function () use ($member, $stageIds): void {
            $this->stages->lockCompany($member->companyId);
            $arranged = StageOrder::arrange($this->stages->forCompany($member->companyId), $stageIds);

            $positions = [];

            foreach ($arranged as $index => $stage) {
                $positions[(int) $stage->id] = $index + 1;
            }

            $this->stages->savePositions($positions);
        });

        return $this->stages->forCompany($member->companyId);
    }
}
