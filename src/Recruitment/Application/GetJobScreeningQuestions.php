<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The screening questions of a job, with the flags and ideal answers, for the members who may see the job. Shown even while the feature is switched off. */
final readonly class GetJobScreeningQuestions
{
    public function __construct(private RecruitmentAccess $access, private JobQuestionRepository $jobQuestions) {}

    /**
     * @return list<JobQuestion>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound when the job does not exist in the company or the member may not see it
     */
    public function execute(Actor $actor, int $jobId): array
    {
        $member = $this->access->viewer($actor);
        $this->access->job($member, $jobId);

        return $this->jobQuestions->forJob($jobId);
    }
}
