<?php

namespace Src\Recruitment\Application\Voice;

use Src\Recruitment\Application\TeamInterviewLookup;
use Src\Recruitment\Domain\Voice\CallRepository;
use Src\Recruitment\Domain\Voice\InterviewCall;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The call log of an interview, for the members who may see the application. */
final readonly class ListInterviewCalls
{
    public function __construct(private TeamInterviewLookup $lookup, private CallRepository $calls) {}

    /**
     * @return list<InterviewCall> oldest first
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $interviewId): array
    {
        $this->lookup->find($actor, $interviewId, permission: CompanyPermission::InterviewsManage);

        return $this->calls->forInterview($interviewId);
    }
}
