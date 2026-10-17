<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\ApplicantGuard;
use Src\Recruitment\Application\RecruitmentAccess;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** Who may listen to the lobby channel of a video interview: its panel and its candidate. Nothing else; ids only travel on the channel. */
final readonly class VideoChannelAuthorizer
{
    public function __construct(
        private InterviewRepository $interviews,
        private ApplicationRepository $applications,
        private RecruitmentAccess $access,
        private ApplicantGuard $guard,
        private VideoSessions $sessions,
    ) {}

    public function mayListen(Actor $actor, int $interviewId): bool
    {
        $interview = $this->interviews->findById($interviewId);

        if (! $interview instanceof Interview || $interview->format !== InterviewFormat::Video) {
            return false;
        }

        $application = $this->applications->find($interview->applicationId);

        if ($application === null) {
            return false;
        }

        try {
            $member = $this->access->anyOf($actor, CompanyPermission::InterviewsManage, CompanyPermission::InterviewsParticipate);

            return $member->companyId === $application->companyId && $this->sessions->isOnPanel($interview, $member->userId);
        } catch (AccessDenied) {
            // Not a company member: the candidate, then.
        }

        try {
            return $this->applications->findOfApplicant($this->guard->userId($actor), $interview->applicationId) !== null;
        } catch (AccessDenied) {
            return false;
        }
    }
}
