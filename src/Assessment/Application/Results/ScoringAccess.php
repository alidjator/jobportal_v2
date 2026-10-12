<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Domain\Answer;
use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\QuestionType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Recruitment\TeamApplications;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The checks and reads of assessor scoring: only an assessor of the invitation scores, and only after the attempt was submitted. */
final readonly class ScoringAccess
{
    public function __construct(
        private VisibleInvitations $visible,
        private AttemptRepository $attempts,
        private AnswerRepository $answers,
        private QuestionRepository $questions,
        private TeamApplications $applications,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-assessor`
     * @throws EntityNotFound
     * @throws StateConflict `attempt-not-submitted`
     */
    public function invitation(Actor $actor, int $invitationId, bool $forUpdate = false): Invitation
    {
        $invitation = $this->visible->find($actor, $invitationId, $forUpdate);

        if ($actor->id === null || ! in_array($actor->id, $invitation->assessorIds, true)) {
            throw new AccessDenied('Only an assessor of this test can score its answers.', 'not-an-assessor');
        }

        return $invitation;
    }

    /**
     * @throws StateConflict `attempt-not-submitted`
     */
    public function submittedAttempt(Invitation $invitation): Attempt
    {
        $attempt = $this->attempts->findByInvitation((int) $invitation->id);

        if ($attempt === null || ! $attempt->isSubmitted()) {
            throw new StateConflict('The candidate has not submitted this test yet.', 'attempt-not-submitted');
        }

        return $attempt;
    }

    /**
     * A score can be given or corrected only while the application is in an assessment stage: once it moved on or was rejected, the company has used the result.
     *
     * @throws StateConflict `scoring-locked`
     */
    public function assertScoringOpen(Actor $actor, Invitation $invitation): void
    {
        if (! $this->applications->find($actor, $invitation->applicationId)->inAssessmentStage) {
            throw new StateConflict('The application has left the assessment stage, so its scores can no longer be changed.', 'scoring-locked');
        }
    }

    /**
     * The questions of the attempt a person scores that have an answer (a written long-text answer or a video), in the order they were asked.
     *
     * @return list<array{question: Question, answer: Answer}>
     */
    public function scorable(Attempt $attempt): array
    {
        $attemptId = (int) $attempt->id;
        $drawn = $this->attempts->drawnOf($attemptId);
        $questions = $this->questions->manyById(array_map(static fn ($item): int => $item->questionId, $drawn));
        $answers = $this->answers->answersOf($attemptId);
        $items = [];

        foreach ($drawn as $item) {
            $question = $questions[$item->questionId] ?? null;
            $answer = $answers[$item->questionId] ?? null;

            if ($question instanceof Question && $answer instanceof Answer && ($question->type === QuestionType::VideoAnswer || ($question->type === QuestionType::LongText && trim((string) $answer->textValue) !== ''))) {
                $items[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $items;
    }
}
