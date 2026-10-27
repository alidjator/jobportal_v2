<?php

namespace Src\Recruitment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\ErrorMessage;

/**
 * What a must-have question expects, set per job. `yes_no`: one answer. `single_choice`: the answer must be one of the accepted options.
 * `multiple_choice`: every ideal option must be among the chosen ones (more are fine). `number`: within a minimum and/or a maximum.
 */
final readonly class IdealAnswer
{
    /** @param  list<int>  $optionIds */
    private function __construct(
        public QuestionType $type,
        public ?bool $yes,
        public array $optionIds,
        public ?int $min,
        public ?int $max,
    ) {}

    /**
     * Builds the ideal answer a caller sent for a question of this type.
     *
     * @param  array<string, mixed>  $input  `{yes}`, `{option_ids}` or `{min, max}` by type
     * @param  list<int>  $questionOptionIds  the ids of the question's own options
     *
     * @throws BusinessRuleViolation `invalid-ideal-answer` with `errors` on `ideal`
     */
    public static function from(QuestionType $type, array $input, array $questionOptionIds): self
    {
        return match ($type) {
            QuestionType::Text => throw self::invalid('A text question cannot be marked must-have.'),
            QuestionType::YesNo => self::yesNo($input),
            QuestionType::SingleChoice, QuestionType::MultipleChoice => self::options($type, $input, $questionOptionIds),
            QuestionType::Number => self::range($input),
        };
    }

    /**
     * Rebuilds an ideal answer that was stored earlier, so it was valid when it was saved.
     *
     * @param  list<int>  $optionIds
     */
    public static function restore(QuestionType $type, ?bool $yes, array $optionIds, ?int $min, ?int $max): self
    {
        return new self($type, $yes, $optionIds, $min, $max);
    }

    /**
     * The ideal answer in the shape a caller sends it (see `from`), so a stored ideal can be written again, for example to copy a preset to a job.
     *
     * @return array<string, mixed>
     */
    public function toInput(): array
    {
        return match ($this->type) {
            QuestionType::YesNo => ['yes' => $this->yes],
            QuestionType::SingleChoice, QuestionType::MultipleChoice => ['option_ids' => $this->optionIds],
            QuestionType::Number => ['min' => $this->min, 'max' => $this->max],
            QuestionType::Text => [],
        };
    }

    /** Whether an answer meets the ideal. The answer is a bool, the id of the chosen option, the ids of the chosen options or a whole number, by type. */
    public function isMetBy(bool|int|array $answer): bool
    {
        return match ($this->type) {
            QuestionType::YesNo => $answer === $this->yes,
            QuestionType::SingleChoice => is_int($answer) && in_array($answer, $this->optionIds, true),
            QuestionType::MultipleChoice => is_array($answer) && array_diff($this->optionIds, $answer) === [],
            QuestionType::Number => is_int($answer) && ($this->min === null || $answer >= $this->min) && ($this->max === null || $answer <= $this->max),
            QuestionType::Text => false,
        };
    }

    /** @param  array<string, mixed>  $input */
    private static function yesNo(array $input): self
    {
        if (! isset($input['yes']) || ! is_bool($input['yes'])) {
            throw self::invalid('The ideal answer of a yes/no question is {"yes": true} or {"yes": false}.');
        }

        return new self(QuestionType::YesNo, $input['yes'], [], null, null);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<int>  $questionOptionIds
     */
    private static function options(QuestionType $type, array $input, array $questionOptionIds): self
    {
        $ids = $input['option_ids'] ?? null;

        if (! is_array($ids) || $ids === [] || array_filter($ids, static fn (mixed $id): bool => ! is_int($id)) !== []) {
            throw self::invalid('The ideal answer of a choice question is a non-empty list of option ids.');
        }

        $ids = array_values(array_unique($ids));

        if (array_diff($ids, $questionOptionIds) !== []) {
            throw self::invalid('Every ideal option must be an option of the question.');
        }

        return new self($type, null, $ids, null, null);
    }

    /** @param  array<string, mixed>  $input */
    private static function range(array $input): self
    {
        $min = $input['min'] ?? null;
        $max = $input['max'] ?? null;

        foreach ([$min, $max] as $bound) {
            if ($bound !== null && (! is_int($bound) || $bound < 0 || $bound > QuestionRules::NUMBER_MAX)) {
                throw self::invalid(new ErrorMessage('The limits of a number must be whole numbers from 0 to :max.', ['max' => QuestionRules::NUMBER_MAX]));
            }
        }

        if ($min === null && $max === null) {
            throw self::invalid('The ideal answer of a number question needs a minimum, a maximum or both.');
        }

        if ($min !== null && $max !== null && $min > $max) {
            throw self::invalid('The minimum cannot be above the maximum.');
        }

        return new self(QuestionType::Number, null, [], $min, $max);
    }

    private static function invalid(string|ErrorMessage $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-ideal-answer', extensions: ['errors' => ['ideal' => [$message]]]);
    }
}
