<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\Navigation;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\SectionFormat;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Finds a question of the open section for a write, and checks that the section's navigation rules let the candidate reach it. */
final readonly class AttemptQuestions
{
    public function __construct(
        private AttemptViews $views,
        private QuestionRepository $questions,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-answer` in a speed section
     * @throws StateConflict `question-locked`
     */
    public function reachable(AttemptContext $context, int $questionId): Question
    {
        $this->assertTakesQuestions($context);

        $index = $this->indexOf($context, $questionId);

        if (! Navigation::canAnswer($context->section(), $context->attempt->questionCursor, $index)) {
            throw new StateConflict('This question is closed: the section does not let you go back to it.', 'question-locked');
        }

        return $this->questions->find($questionId) ?? throw EntityNotFound::of('Question', $questionId);
    }

    /** @throws BusinessRuleViolation `invalid-answer` in a speed section */
    public function assertTakesQuestions(AttemptContext $context): void
    {
        if ($context->section()->format === SectionFormat::SpeedColumns) {
            throw new BusinessRuleViolation('The open section has no questions.', 'invalid-answer', extensions: ['errors' => ['question' => ['The open section has no questions.']]]);
        }
    }

    /**
     * @throws EntityNotFound the question is not one of the open section
     */
    private function indexOf(AttemptContext $context, int $questionId): int
    {
        foreach ($this->views->drawnOfSection($context) as $index => $item) {
            if ($item->questionId === $questionId) {
                return $index;
            }
        }

        throw EntityNotFound::of('Question', $questionId);
    }
}
