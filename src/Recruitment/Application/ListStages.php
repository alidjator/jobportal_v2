<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The stages of the caller's company in their order, for company admins and recruiters. */
final readonly class ListStages
{
    public function __construct(private RecruitmentAccess $access, private StageRepository $stages) {}

    /**
     * @return list<Stage>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        return $this->stages->forCompany($this->access->member($actor)->companyId);
    }
}
