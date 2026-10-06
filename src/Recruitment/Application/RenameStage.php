<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company admin renames a stage, a default one included. Names are unique in the company, whatever the letter case. */
final readonly class RenameStage
{
    public function __construct(
        private StageAccess $access,
        private StageRepository $stages,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     * @throws EntityNotFound when the stage is not one of the company's
     * @throws StateConflict `stage-name-taken`
     */
    public function execute(Actor $actor, int $stageId, string $name): Stage
    {
        $member = $this->access->companyAdmin($actor);
        $now = $this->clock->now();

        return $this->transactions->run(function () use ($member, $stageId, $name, $now): Stage {
            $this->stages->lockCompany($member->companyId);

            foreach ($this->stages->forCompany($member->companyId) as $stage) {
                if ($stage->id === $stageId) {
                    $this->stages->rename($stageId, $name, $now);

                    return new Stage($stage->companyId, $stage->type, $name, $stage->position, $stage->isDefault, $stage->id);
                }
            }

            throw EntityNotFound::of('Stage', $stageId);
        });
    }
}
