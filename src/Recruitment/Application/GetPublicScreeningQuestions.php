<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The questions an applicant answers for a live job, in order. A job that is not public (or whose company is not visible) does not exist, as on the job page.
 * While the feature is switched off for the company the list is empty.
 */
final readonly class GetPublicScreeningQuestions
{
    public function __construct(private JobDirectory $jobs, private ScreeningSwitch $switch, private JobQuestionRepository $jobQuestions) {}

    /**
     * @return list<PublicScreeningQuestion>
     *
     * @throws EntityNotFound
     */
    public function execute(int $jobId): array
    {
        $companyId = $this->jobs->publicCompanyId($jobId) ?? throw EntityNotFound::of('Job', $jobId);

        if (! $this->switch->isOn($companyId)) {
            return [];
        }

        return array_map(static fn (JobQuestion $question): PublicScreeningQuestion => new PublicScreeningQuestion(
            $question->question->id, $question->question->type, $question->question->text, $question->question->options, $question->isRequired, $question->position,
        ), $this->jobQuestions->forJob($jobId));
    }
}
