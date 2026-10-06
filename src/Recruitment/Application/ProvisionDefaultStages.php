<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;

/** Defaults cannot be deleted, so a company that has any stage already has all seven: nothing is added then. */
final readonly class ProvisionDefaultStages implements DefaultStageProvisioner
{
    public function __construct(private StageRepository $stages, private Clock $clock) {}

    public function provision(int $companyId): void
    {
        if ($this->stages->companyHasStages($companyId)) {
            return;
        }

        $this->stages->addMany(Stage::defaultsFor($companyId), $this->clock->now());
    }
}
