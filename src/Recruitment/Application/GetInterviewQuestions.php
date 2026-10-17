<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewQuestionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The questions of an interview with the interviewer guidance, for the members who may see its application (also shown on screen during a call). */
final readonly class GetInterviewQuestions
{
    public function __construct(private TeamInterviewLookup $lookup, private InterviewQuestionRepository $questions) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $interviewId): InterviewQuestionList
    {
        $interview = $this->lookup->find($actor, $interviewId, panelOpens: true)->interview;

        return new InterviewQuestionList(
            (int) $interview->id,
            $interview->kitId,
            $interview->kitName,
            $this->questions->withIds((int) $interview->id),
            $this->questions->scoringStarted((int) $interview->id),
        );
    }
}
