<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageOrder;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company admin adds an extra stage (screening, interview or offer). It goes after the other active stages, before the terminal ones. */
final readonly class CreateStage
{
    public function __construct(
        private StageAccess $access,
        private StageRepository $stages,
        private Settings $settings,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     * @throws BusinessRuleViolation `stage-type-not-allowed`
     * @throws StateConflict `stage-limit-reached` or `stage-name-taken`
     */
    public function execute(Actor $actor, string $name, StageType $type): Stage
    {
        $member = $this->access->companyAdmin($actor);

        if (! $type->allowsExtraStages()) {
            throw new BusinessRuleViolation('Extra stages can only be of the type screening, interview or offer.', 'stage-type-not-allowed');
        }

        $now = $this->clock->now();

        $id = $this->transactions->run(function () use ($member, $name, $type, $now): int {
            $this->stages->lockCompany($member->companyId);
            $current = $this->stages->forCompany($member->companyId);

            if (count($current) >= $this->settings->integer(RecruitmentSettings::MAX_STAGES, $member->companyId)) {
                throw new StateConflict('The company has reached its limit of stages.', 'stage-limit-reached');
            }

            $index = StageOrder::insertionIndex($current);
            $id = $this->stages->add(new Stage($member->companyId, $type, $name, $index + 1, false), $now);

            $positions = [];

            foreach (array_slice($current, $index) as $offset => $stage) {
                $positions[(int) $stage->id] = $index + 2 + $offset;
            }

            $this->stages->savePositions($positions);

            return $id;
        });

        foreach ($this->stages->forCompany($member->companyId) as $stage) {
            if ($stage->id === $id) {
                return $stage;
            }
        }

        throw new \LogicException('The new stage was not found.');
    }
}
