<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageOrder;
use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** A company admin replaces the whole order of the stages. The list must name every stage once and obey the order rules. */
final readonly class ReorderStages
{
    public function __construct(private RecruitmentAccess $access, private StageRepository $stages, private HiringManagerVisibility $visibility, private TransactionManager $transactions, private Clock $clock) {}

    /**
     * @param  list<int>  $stageIds
     * @return list<Stage> the stages in their new order
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws BusinessRuleViolation `invalid-stage-order`
     */
    public function execute(Actor $actor, array $stageIds): array
    {
        $member = $this->access->permitted($actor, CompanyPermission::StagesManage);

        $this->transactions->run(function () use ($member, $stageIds): void {
            $this->stages->lockCompany($member->companyId);
            $arranged = StageOrder::arrange($this->stages->forCompany($member->companyId), $stageIds);

            $positions = [];

            foreach ($arranged as $index => $stage) {
                $positions[(int) $stage->id] = $index + 1;
            }

            $this->stages->savePositions($positions);
            $this->visibility->refreshCompany($member->companyId, $this->clock->now());
        });

        return $this->stages->forCompany($member->companyId);
    }
}
