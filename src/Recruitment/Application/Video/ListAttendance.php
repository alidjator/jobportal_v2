<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\TeamInterviewLookup;
use Src\Recruitment\Domain\Video\AttendanceRepository;
use Src\Recruitment\Domain\Video\InterviewAttendance;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Who was in the video room and when, for the members who may see the application. */
final readonly class ListAttendance
{
    public function __construct(private TeamInterviewLookup $lookup, private AttendanceRepository $attendance) {}

    /**
     * @return list<InterviewAttendance>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $interviewId): array
    {
        $this->lookup->assertSatOn($this->lookup->find($actor, $interviewId, permission: CompanyPermission::InterviewsManage, panelOpens: true));

        return $this->attendance->forInterview($interviewId);
    }
}
