<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Finds an interview stage of the member's company: the one a job's default kit is set for, or the one a comparison of interview results is limited to. */
final readonly class JobInterviewStages
{
    public function __construct(private StageRepository $stages) {}

    /**
     * @throws EntityNotFound the stage is not one of the company's
     * @throws BusinessRuleViolation `stage-not-interview`
     */
    public function find(RecruitmentMember $member, int $stageId, string $message = 'A default kit can only be set for an interview stage.'): Stage
    {
        foreach ($this->stages->forCompany($member->companyId) as $stage) {
            if ($stage->id !== $stageId) {
                continue;
            }

            if ($stage->type !== StageType::Interview) {
                throw new BusinessRuleViolation($message, 'stage-not-interview', extensions: ['errors' => ['stage_id' => [$message]]]);
            }

            return $stage;
        }

        throw EntityNotFound::of('Stage', $stageId);
    }
}
