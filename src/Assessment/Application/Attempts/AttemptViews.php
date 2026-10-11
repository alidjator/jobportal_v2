<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\AttemptQuestion;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\ColumnDigits;
use Src\Assessment\Domain\Navigation;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\SectionFormat;
use Src\Assessment\Domain\SpeedColumnResult;

/** Builds what the candidate sees of an attempt at this moment. */
final readonly class AttemptViews
{
    public function __construct(
        private AttemptRepository $attempts,
        private AnswerRepository $answers,
        private QuestionRepository $questions,
    ) {}

    public function build(AttemptContext $context): AttemptView
    {
        if (! $context->isOpen()) {
            return new AttemptView($context, 0, [], null);
        }

        $section = $context->section();

        if ($section->format === SectionFormat::SpeedColumns) {
            return new AttemptView($context, 0, [], $this->speed($context));
        }

        $drawn = $this->drawnOfSection($context);
        $visible = Navigation::visible($section, $context->attempt->questionCursor, count($drawn));
        $questions = $this->questions->manyById(array_values(array_map(static fn (int $index): int => $drawn[$index]->questionId, $visible)));
        $attemptId = (int) $context->attempt->id;
        $answers = $this->answers->answersOf($attemptId);
        $drawings = $this->answers->drawingsOf($attemptId);
        $delivered = [];

        foreach ($visible as $index) {
            $id = $drawn[$index]->questionId;

            if (isset($questions[$id])) {
                $delivered[] = new DeliveredQuestion($index, $questions[$id], $answers[$id] ?? null, $drawings[$id] ?? null);
            }
        }

        return new AttemptView($context, count($drawn), $delivered, null);
    }

    /** @return list<AttemptQuestion> the questions of the open section, in the order they are asked */
    public function drawnOfSection(AttemptContext $context): array
    {
        $sectionId = (int) $context->section()->id;

        return array_values(array_filter(
            $this->attempts->drawnOf((int) $context->attempt->id),
            static fn (AttemptQuestion $item): bool => $item->sectionId === $sectionId,
        ));
    }

    /** @return array{column: int, columns: int, digits: list<int>, column_ends_at: \DateTimeImmutable, submitted_columns: list<int>} */
    private function speed(AttemptContext $context): array
    {
        $section = $context->section();
        $columns = $section->settings['columns'];
        $seconds = $section->settings['seconds_per_column'];
        $elapsed = $context->now->getTimestamp() - $context->attempt->sectionOpenedAt->getTimestamp();
        $column = min($columns, intdiv(max($elapsed, 0), $seconds) + 1);

        $submitted = array_values(array_map(
            static fn (SpeedColumnResult $result): int => $result->columnNo,
            array_filter($this->answers->columnsOf((int) $context->attempt->id), static fn (SpeedColumnResult $result): bool => $result->sectionId === $section->id),
        ));

        return [
            'column' => $column,
            'columns' => $columns,
            'digits' => ColumnDigits::of($context->attempt->seed, (int) $section->id, $column, $section->settings['digits_per_column']),
            'column_ends_at' => $context->attempt->sectionOpenedAt->modify('+'.($column * $seconds).' seconds'),
            'submitted_columns' => $submitted,
        ];
    }
}
