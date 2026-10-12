<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Domain\InterviewKitRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The company's kit library, by name, for every member. */
final readonly class ListInterviewKits
{
    public function __construct(private RecruitmentAccess $access, private InterviewKitRepository $kits) {}

    /**
     * @return list<InterviewKit>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        return $this->kits->forCompany($this->access->member($actor)->companyId);
    }
}
