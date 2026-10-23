<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Application\Attempts\AttemptContexts;
use Src\Assessment\Application\Attempts\ScoreAttempt;
use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\Events\ResultFinalized;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\ResultStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * An assessor scores one written or video answer with a whole number from 0 to the points of the question; scoring again corrects the earlier score, until the application leaves its assessment stage (moved on or rejected). The result is worked
 * out again at once, so when the last written answer gets its score the result becomes final with its percentage and band.
 */
final readonly class ScoreAnswer
{
    public function __construct(
        private ScoringAccess $access,
        private AnswerRepository $answers,
        private AttemptContexts $contexts,
        private ScoreAttempt $scoring,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-assessor`
     * @throws EntityNotFound
     * @throws StateConflict `attempt-not-submitted` or `scoring-locked`
     * @throws BusinessRuleViolation `invalid-score`
     */
    public function execute(Actor $actor, int $invitationId, int $questionId, int $score): ScoringItem
    {
        $finalized = null;

        $item = $this->transactions->run(function () use ($actor, $invitationId, $questionId, $score, &$finalized): ScoringItem {
            $invitation = $this->access->invitation($actor, $invitationId, forUpdate: true);
            $attempt = $this->access->submittedAttempt($invitation);
            $this->access->assertScoringOpen($actor, $invitation);

            $entry = null;

            foreach ($this->access->scorable($attempt) as $candidate) {
                if ($candidate['question']->id === $questionId) {
                    $entry = $candidate;
                }
            }

            if ($entry === null) {
                throw EntityNotFound::of('Answer', $questionId);
            }

            $points = $entry['question']->points;

            if ($score < 0 || $score > $points) {
                throw new BusinessRuleViolation(
                    'The score must be a whole number from 0 to :points.', 'invalid-score',
                    extensions: ['errors' => ['score' => ['The score must be a whole number from 0 to :points.']]], parameters: ['points' => $points],
                );
            }

            $this->answers->saveAssessorScore((int) $attempt->id, $questionId, (float) $score, (int) $actor->id);

            $context = $this->contexts->load($invitation, $attempt, $this->clock->now());
            $result = $this->scoring->execute($attempt, $invitation, $context->test, $context->sections);

            if ($result->status === ResultStatus::Scored && $result->percentage !== null) {
                $finalized = new ResultFinalized((int) $invitation->id, $invitation->applicationId, $invitation->jobId, $invitation->companyId, $result->percentage, $result->integrityTier->value, $this->clock->now());
            }

            return new ScoringItem($questionId, $entry['question']->text, $points, $entry['question']->type === QuestionType::LongText ? (string) $entry['answer']->textValue : null, (float) $score, $entry['question']->type);
        });

        // The result may have just become final: the automatic advance of the job's default test looks at it once the scores are saved.
        if ($finalized !== null) {
            $this->events->dispatch([$finalized]);
        }

        return $item;
    }
}
