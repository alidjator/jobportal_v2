<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin deletes an extra stage; the default stages can never be deleted. Applications do not exist yet: when they arrive, the ones in
 * the deleted stage move to the default stage of the same type in this same transaction.
 */
final readonly class DeleteStage
{
    public function __construct(private StageAccess $access, private StageRepository $stages, private TransactionManager $transactions) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     * @throws EntityNotFound when the stage is not one of the company's
     * @throws StateConflict `default-stage-cannot-be-deleted`
     */
    public function execute(Actor $actor, int $stageId): void
    {
        $member = $this->access->companyAdmin($actor);

        $this->transactions->run(function () use ($member, $stageId): void {
            $this->stages->lockCompany($member->companyId);
            $remaining = [];
            $found = false;

            foreach ($this->stages->forCompany($member->companyId) as $stage) {
                if ($stage->id !== $stageId) {
                    $remaining[] = $stage;

                    continue;
                }

                if ($stage->isDefault) {
                    throw new StateConflict('A default stage cannot be deleted.', 'default-stage-cannot-be-deleted');
                }

                $found = true;
            }

            if (! $found) {
                throw EntityNotFound::of('Stage', $stageId);
            }

            $this->stages->delete($stageId);

            $positions = [];

            foreach ($remaining as $index => $stage) {
                $positions[(int) $stage->id] = $index + 1;
            }

            $this->stages->savePositions($positions);
        });
    }
}
