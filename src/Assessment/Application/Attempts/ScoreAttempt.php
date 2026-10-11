<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\Answer;
use Src\Assessment\Domain\AnswerGrader;
use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\AttemptResult;
use Src\Assessment\Domain\IntegrityTier;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\ResultBand;
use Src\Assessment\Domain\ResultStatus;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\SectionFormat;
use Src\Assessment\Domain\SpeedColumnResult;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Settings\Settings;

/**
 * Scores an attempt from its answers and keeps the result. A skill test gives points, a percentage and a band (the band waits while a long-text answer has no
 * score yet). A psychological battery gives the raw score of each scale; norms and the report come later. It can run again after an assessor scored an
 * answer: it only reads the stored answers, so the result follows them.
 */
final readonly class ScoreAttempt
{
    public function __construct(
        private AttemptRepository $attempts,
        private AnswerRepository $answers,
        private AttemptOutcomeRepository $outcomes,
        private QuestionRepository $questions,
        private Settings $settings,
    ) {}

    /** @param  list<Section>  $sections */
    public function execute(Attempt $attempt, Invitation $invitation, Test $test, array $sections): AttemptResult
    {
        $attemptId = (int) $attempt->id;
        $drawn = $this->attempts->drawnOf($attemptId);
        $questions = $this->questions->manyById(array_values(array_map(static fn ($item): int => $item->questionId, $drawn)));
        $answers = $this->answers->answersOf($attemptId);
        $columns = $this->answers->columnsOf($attemptId);
        $drawings = $this->answers->drawingsOf($attemptId);

        // Every answer that can be scored without a person gets its score now; a long-text answer keeps the score its assessor gave.
        $scores = [];
        $byQuestion = [];

        foreach ($drawn as $item) {
            $question = $questions[$item->questionId] ?? null;

            if (! $question instanceof Question) {
                continue;
            }

            $answer = $answers[$item->questionId] ?? null;
            $score = AnswerGrader::grade($question, $answer) ?? $answer?->score;

            if ($answer instanceof Answer && $question->type !== QuestionType::LongText) {
                $scores[$item->questionId] = $score;
            }

            $byQuestion[$item->sectionId][] = [$question, $answer, $score];
        }

        $this->answers->saveScores($attemptId, $scores);

        $tier = IntegrityTier::forCount($this->outcomes->eventCount($attemptId));
        $sectionScores = [];
        $earned = 0.0;
        $max = 0.0;
        $pending = false;
        $scales = [];

        foreach ($sections as $section) {
            $entries = $byQuestion[(int) $section->id] ?? [];

            if ($section->format === SectionFormat::SpeedColumns) {
                $sectionScores[] = $this->speedScores($section, $columns);

                continue;
            }

            $sectionEarned = 0.0;
            $sectionMax = 0.0;
            $answered = 0;

            foreach ($entries as [$question, $answer, $score]) {
                $answered += $answer instanceof Answer || isset($drawings[(int) $question->id]) ? 1 : 0;
                $sectionMax += $question->points;
                $sectionEarned += $score ?? 0.0;

                if ($question->type === QuestionType::LongText && $answer instanceof Answer && trim((string) $answer->textValue) !== '' && $answer->score === null) {
                    $pending = true;
                }

                if ($test->kind() === TestKind::Psychological) {
                    foreach ($this->scalesOf($question) as $key) {
                        $scales[$key] ??= 0.0;
                    }

                    $scale = AnswerGrader::scaleOf($question, $answer);

                    if ($scale !== null && $score !== null) {
                        $scales[$scale] += $score;
                    }
                }
            }

            $earned += $sectionEarned;
            $max += $sectionMax;
            $sectionScores[] = [
                'section_id' => (int) $section->id,
                'format' => $section->format->value,
                'answered' => $answered,
                'total' => count($entries),
                'earned' => $sectionEarned,
                'max' => $sectionMax,
            ];
        }

        $result = $test->kind() === TestKind::Psychological
            ? new AttemptResult($attemptId, ResultStatus::Scored, $sectionScores, $scales, null, null, null, null, $tier)
            : $this->skillResult($attemptId, $invitation->companyId, $sectionScores, $earned, $max, $pending, $tier);

        $this->outcomes->saveResult($result);

        return $result;
    }

    /** @param  list<array<string, mixed>>  $sectionScores */
    private function skillResult(int $attemptId, int $companyId, array $sectionScores, float $earned, float $max, bool $pending, IntegrityTier $tier): AttemptResult
    {
        $percentage = $max > 0 && ! $pending ? round($earned / $max * 100, 2) : null;
        $band = $percentage === null ? null : ResultBand::forPercentage(
            $percentage,
            $this->settings->integer(AssessmentSettings::BAND_FAIR_FROM, $companyId),
            $this->settings->integer(AssessmentSettings::BAND_GOOD_FROM, $companyId),
            $this->settings->integer(AssessmentSettings::BAND_EXCELLENT_FROM, $companyId),
        );

        return new AttemptResult(
            $attemptId, $pending ? ResultStatus::PendingScoring : ResultStatus::Scored, $sectionScores, null, $earned, $max, $percentage, $band, $tier,
        );
    }

    /** @return list<string> the scales a question can score for */
    private function scalesOf(Question $question): array
    {
        $keys = $question->scaleKey === null ? [] : [$question->scaleKey];

        foreach ($question->options as $option) {
            if ($option->scaleKey !== null) {
                $keys[] = $option->scaleKey;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<SpeedColumnResult>  $columns
     * @return array<string, mixed>
     */
    private function speedScores(Section $section, array $columns): array
    {
        $mine = array_values(array_filter($columns, static fn (SpeedColumnResult $column): bool => $column->sectionId === $section->id));

        return [
            'section_id' => (int) $section->id,
            'format' => $section->format->value,
            'columns' => $section->settings['columns'] ?? 0,
            'attempted' => array_sum(array_map(static fn (SpeedColumnResult $column): int => $column->attempted, $mine)),
            'correct' => array_sum(array_map(static fn (SpeedColumnResult $column): int => $column->correct, $mine)),
        ];
    }
}
