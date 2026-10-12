<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\QuestionRules;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\Test;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Turns what an author submitted into a valid question of a section, applying the rules of its type, test kind and section format. */
final readonly class QuestionFactory
{
    public function __construct(private QuestionRepository $questions) {}

    /**
     * @throws BusinessRuleViolation `invalid-question`
     */
    public function build(Test $test, Section $section, QuestionDraft $draft, ?int $id = null, int $position = 0): Question
    {
        $checked = QuestionRules::check($test->kind(), $section->format, $draft->type, $draft->text, $draft->points, $draft->scaleKey, $draft->options, $draft->thinkSeconds, $draft->answerSeconds, $draft->maxRetakes);

        if ($draft->isSample && $this->questions->sampleTaken((int) $test->id, $draft->type, $id)) {
            $message = 'Another question of this type is already the sample of the test.';

            throw new BusinessRuleViolation($message, 'invalid-question', extensions: ['errors' => ['is_sample' => [$message]]]);
        }

        return new Question($id, (int) $section->id, $position, $draft->type, $checked['text'], $checked['points'], $checked['scaleKey'], $draft->isSample, $checked['options'], $checked['thinkSeconds'], $checked['answerSeconds'], $checked['maxRetakes']);
    }
}
