<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/**
 * One panel member's scorecard of an interview: a score from 1 to 5 per question, a final recommendation and a note. The result is the average of the answered
 * questions weighted by the question weights; a skipped question does not count. A draft may lack the recommendation, a sent scorecard may not.
 */
final readonly class Scorecard
{
    public const int NOTE_MAX = 2000;

    public const int ANSWER_NOTE_MAX = 1000;

    public const int SCORE_MIN = 1;

    public const int SCORE_MAX = 5;

    /** @param  list<ScorecardAnswer>  $answers */
    public function __construct(
        public int $interviewId,
        public int $memberId,
        public ScorecardStatus $status,
        public ?Recommendation $recommendation,
        public ?string $note,
        public array $answers,
        public ?float $score,
        public ?DateTimeImmutable $submittedAt = null,
        public ?DateTimeImmutable $editedAt = null,
        public ?int $id = null,
    ) {}

    public static function blank(int $interviewId, int $memberId): self
    {
        return new self($interviewId, $memberId, ScorecardStatus::Draft, null, null, [], null);
    }

    public function isSubmitted(): bool
    {
        return $this->status === ScorecardStatus::Submitted;
    }

    /**
     * The scorecard with new content. A draft stays a draft; a sent scorecard stays sent, needs its recommendation and gets an edit time when the content changed.
     *
     * @param  list<array{question_id: int, score: ?int, note: ?string}>  $answers
     * @param  array<int, int>  $weights  the weight of each question of the interview, by question id
     *
     * @throws BusinessRuleViolation `invalid-scorecard` or `recommendation-required`
     */
    public function withContent(?Recommendation $recommendation, ?string $note, array $answers, array $weights, DateTimeImmutable $now): self
    {
        $note = self::text($note);
        $checked = [];
        $seen = [];
        $errors = [];

        if ($note !== null && mb_strlen($note) > self::NOTE_MAX) {
            $errors['note'] = [new ErrorMessage('The note can have at most :max characters.', ['max' => self::NOTE_MAX])];
        }

        foreach ($answers as $index => $answer) {
            $answerNote = self::text($answer['note']);

            if (! isset($weights[$answer['question_id']])) {
                $errors["answers.{$index}.question_id"] = ['This question does not belong to the interview.'];
            } elseif (isset($seen[$answer['question_id']])) {
                $errors["answers.{$index}.question_id"] = ['This question is answered twice.'];
            }

            $seen[$answer['question_id']] = true;

            if ($answer['score'] !== null && ($answer['score'] < self::SCORE_MIN || $answer['score'] > self::SCORE_MAX)) {
                $errors["answers.{$index}.score"] = [new ErrorMessage('The score must be between :min and :max.', ['min' => self::SCORE_MIN, 'max' => self::SCORE_MAX])];
            }

            if ($answerNote !== null && mb_strlen($answerNote) > self::ANSWER_NOTE_MAX) {
                $errors["answers.{$index}.note"] = [new ErrorMessage('The note can have at most :max characters.', ['max' => self::ANSWER_NOTE_MAX])];
            }

            // An answer with neither a score nor a note is a skipped question, the same as no answer.
            if ($answer['score'] !== null || $answerNote !== null) {
                $checked[$answer['question_id']] = new ScorecardAnswer($answer['question_id'], $answer['score'], $answerNote);
            }
        }

        ksort($checked);

        if ($errors !== []) {
            throw new BusinessRuleViolation(reset($errors)[0], 'invalid-scorecard', extensions: ['errors' => $errors]);
        }

        if ($this->isSubmitted() && ! $recommendation instanceof Recommendation) {
            throw self::recommendationRequired();
        }

        $changed = new self(
            $this->interviewId,
            $this->memberId,
            $this->status,
            $recommendation,
            $note,
            array_values($checked),
            self::average(array_values($checked), $weights),
            $this->submittedAt,
            $this->editedAt,
            $this->id,
        );

        return $this->isSubmitted() && $this->content() !== $changed->content() ? $changed->edited($now) : $changed;
    }

    /**
     * Sends the draft.
     *
     * @throws BusinessRuleViolation `recommendation-required`
     */
    public function submit(DateTimeImmutable $now): self
    {
        if (! $this->recommendation instanceof Recommendation) {
            throw self::recommendationRequired();
        }

        return new self($this->interviewId, $this->memberId, ScorecardStatus::Submitted, $this->recommendation, $this->note, $this->answers, $this->score, $now, $this->editedAt, $this->id);
    }

    /**
     * What a revision keeps of the scorecard: its content without the times.
     *
     * @return array{recommendation: ?string, note: ?string, score: ?float, answers: list<array{question_id: int, score: ?int, note: ?string}>}
     */
    public function content(): array
    {
        return [
            'recommendation' => $this->recommendation?->value,
            'note' => $this->note,
            'score' => $this->score,
            'answers' => array_map(static fn (ScorecardAnswer $answer): array => ['question_id' => $answer->questionId, 'score' => $answer->score, 'note' => $answer->note], $this->answers),
        ];
    }

    /**
     * The weighted average of the answered questions, to two decimals; null when none was scored.
     *
     * @param  list<ScorecardAnswer>  $answers
     * @param  array<int, int>  $weights
     */
    public static function average(array $answers, array $weights): ?float
    {
        $sum = 0;
        $total = 0;

        foreach ($answers as $answer) {
            if ($answer->score !== null) {
                $sum += $answer->score * $weights[$answer->questionId];
                $total += $weights[$answer->questionId];
            }
        }

        return $total === 0 ? null : round($sum / $total, 2);
    }

    private function edited(DateTimeImmutable $at): self
    {
        return new self($this->interviewId, $this->memberId, $this->status, $this->recommendation, $this->note, $this->answers, $this->score, $this->submittedAt, $at, $this->id);
    }

    private static function recommendationRequired(): BusinessRuleViolation
    {
        $message = 'Give a recommendation before sending the scorecard.';

        return new BusinessRuleViolation($message, 'recommendation-required', extensions: ['errors' => ['recommendation' => [$message]]]);
    }

    private static function text(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
