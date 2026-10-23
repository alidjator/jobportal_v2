<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageOrder;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;

/**
 * A company without stages gets all eight. Defaults cannot be deleted, so a company that has stages only lacks the default of a type added later:
 * the `assessment` stage goes right after the default `screening` stage and shifts the stages behind it.
 */
final readonly class ProvisionDefaultStages implements DefaultStageProvisioner
{
    public function __construct(private StageRepository $stages, private Clock $clock) {}

    public function provision(int $companyId): void
    {
        $now = $this->clock->now();

        if (! $this->stages->companyHasStages($companyId)) {
            $this->stages->addMany(Stage::defaultsFor($companyId), $now);

            return;
        }

        $this->stages->lockCompany($companyId);
        $current = $this->stages->forCompany($companyId);

        if ($this->hasDefault($current, StageType::Assessment)) {
            return;
        }

        $index = $this->indexAfterDefault($current, StageType::Screening);
        $this->addAt($companyId, $current, $index, $now);
    }

    /**
     * @param  list<Stage>  $current
     */
    private function addAt(int $companyId, array $current, int $index, DateTimeImmutable $now): void
    {
        $this->stages->add(new Stage($companyId, StageType::Assessment, $this->freeName($current), $index + 1, true), $now);

        $positions = [];

        foreach (array_slice($current, $index) as $offset => $stage) {
            $positions[(int) $stage->id] = $index + 2 + $offset;
        }

        $this->stages->savePositions($positions);
    }

    /**
     * The default name, or a distinct one when an extra stage of the company already uses it (names are unique per company, ignoring case).
     *
     * @param  list<Stage>  $current
     */
    private function freeName(array $current): string
    {
        $name = StageType::Assessment->defaultName();
        $taken = array_map(static fn (Stage $stage): string => mb_strtolower($stage->name), $current);

        return in_array(mb_strtolower($name), $taken, true) ? $name.' (bawaan)' : $name;
    }

    /** @param  list<Stage>  $stages */
    private function hasDefault(array $stages, StageType $type): bool
    {
        foreach ($stages as $stage) {
            if ($stage->isDefault && $stage->type === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * The index just behind the default stage of the type, or the place of a new extra stage when it is missing.
     *
     * @param  list<Stage>  $stages
     */
    private function indexAfterDefault(array $stages, StageType $type): int
    {
        foreach ($stages as $index => $stage) {
            if ($stage->isDefault && $stage->type === $type) {
                return $index + 1;
            }
        }

        return StageOrder::insertionIndex($stages);
    }
}
