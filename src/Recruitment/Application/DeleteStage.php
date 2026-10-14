<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin deletes an extra stage; the default stages can never be deleted. The applications in the deleted stage move to the default stage of
 * the same type in this same transaction, each with a history entry by the system.
 */
final readonly class DeleteStage
{
    public function __construct(
        private RecruitmentAccess $access,
        private StageRepository $stages,
        private ApplicationRepository $applications,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound when the stage is not one of the company's
     * @throws StateConflict `default-stage-cannot-be-deleted` or `applications-unavailable`
     */
    public function execute(Actor $actor, int $stageId): void
    {
        $member = $this->access->permitted($actor, CompanyPermission::StagesManage);

        $this->transactions->run(function () use ($member, $stageId): void {
            $this->stages->lockCompany($member->companyId);
            $remaining = [];
            $deleted = null;
            $defaults = [];

            foreach ($this->stages->forCompany($member->companyId) as $stage) {
                if ($stage->id !== $stageId) {
                    $remaining[] = $stage;

                    if ($stage->isDefault) {
                        $defaults[$stage->type->value] = (int) $stage->id;
                    }

                    continue;
                }

                if ($stage->isDefault) {
                    throw new StateConflict('A default stage cannot be deleted.', 'default-stage-cannot-be-deleted');
                }

                $deleted = $stage;
            }

            if ($deleted === null) {
                throw EntityNotFound::of('Stage', $stageId);
            }

            $this->applications->moveStage($stageId, $defaults[$deleted->type->value] ?? throw new StateConflict('The company has no default stage of this type.', 'applications-unavailable'), $deleted->type, $this->clock->now());
            $this->stages->delete($stageId);

            $positions = [];

            foreach ($remaining as $index => $stage) {
                $positions[(int) $stage->id] = $index + 1;
            }

            $this->stages->savePositions($positions);
        });
    }
}
