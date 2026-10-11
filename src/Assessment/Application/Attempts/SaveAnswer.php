<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\AnswerRules;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Saves, or with `clear` removes, the candidate's answer to a question of the open section. The last answer saved before time runs out is the one that counts. */
final readonly class SaveAnswer
{
    public function __construct(
        private AttemptGate $gate,
        private AttemptQuestions $reach,
        private AnswerRepository $answers,
    ) {}

    /**
     * @param  list<int>|null  $optionPositions
     *
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-answer`
     * @throws StateConflict `attempt-not-started`, `attempt-closed` or `question-locked`
     */
    public function save(Actor $actor, int $invitationId, int $questionId, ?array $optionPositions, int|float|null $number, ?string $text, ?string $ip): void
    {
        $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($questionId, $optionPositions, $number, $text): void {
            $question = $this->reach->reachable($context, $questionId);

            $this->answers->saveAnswer((int) $context->attempt->id, AnswerRules::answer($question, $optionPositions, $number, $text));
        });
    }

    /**
     * @throws EntityNotFound
     * @throws StateConflict `attempt-not-started`, `attempt-closed` or `question-locked`
     */
    public function clear(Actor $actor, int $invitationId, int $questionId, ?string $ip): void
    {
        $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($questionId): void {
            $this->reach->reachable($context, $questionId);

            $this->answers->deleteAnswer((int) $context->attempt->id, $questionId);
        });
    }
}
