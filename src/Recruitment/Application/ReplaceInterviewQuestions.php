<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\InterviewQuestionRepository;
use Src\Recruitment\Domain\KitQuestion;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Replaces the questions an interview keeps, for the member who scheduled it, its main interviewer or a company admin, until the first scorecard draft
 * exists (from then on every panel member scores the same questions). The list may be empty: the scorecard then holds only the recommendation.
 */
final readonly class ReplaceInterviewQuestions
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private InterviewQuestionRepository $questions,
        private GetInterviewQuestions $get,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<array{text: string, guidance: ?string, weight: int}>  $questions
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `interview-edit-denied`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-kit`
     * @throws StateConflict `scoring-started`
     */
    public function execute(Actor $actor, int $interviewId, array $questions): InterviewQuestionList
    {
        $team = $this->lookup->find($actor, $interviewId);
        $interview = $team->interview;

        if (! $team->member->isCompanyAdmin && $team->member->userId !== $interview->createdBy && $team->member->userId !== $interview->interviewerId) {
            throw new AccessDenied('Only the member who scheduled the interview, its interviewer or a company admin can change its questions.', 'interview-edit-denied');
        }

        $checked = KitQuestion::many($questions, 0);

        $this->transactions->run(function () use ($interview, $checked): void {
            if ($this->questions->scoringStarted((int) $interview->id)) {
                throw new StateConflict('A panel member already started a scorecard, so the questions can no longer change.', 'scoring-started');
            }

            $this->questions->replace((int) $interview->id, $checked);
        });

        return $this->get->execute($actor, $interviewId);
    }
}
