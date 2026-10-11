<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Navigation;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The candidate opens another question of the open section, as far as the section's rules allow. */
final readonly class MoveToQuestion
{
    public function __construct(
        private AttemptGate $gate,
        private AttemptQuestions $reach,
        private AttemptViews $views,
        private AttemptRepository $attempts,
        private AnswerRepository $answers,
    ) {}

    /**
     * @throws StateConflict `attempt-not-started` or `attempt-closed`
     */
    public function execute(Actor $actor, int $invitationId, int $index, ?string $ip): AttemptView
    {
        return $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($index): AttemptView {
            $this->reach->assertTakesQuestions($context);

            $drawn = $this->views->drawnOfSection($context);
            $cursor = $context->attempt->questionCursor;
            $currentId = isset($drawn[$cursor]) ? $drawn[$cursor]->questionId : null;
            $answered = $currentId !== null && isset($this->answers->answersOf((int) $context->attempt->id)[$currentId]);

            Navigation::assertCanMove($context->section(), $cursor, $index, count($drawn), $answered);

            $moved = $context->attempt->movedTo($index);
            $this->attempts->update($moved);

            return $this->views->build($context->withAttempt($moved));
        });
    }
}
