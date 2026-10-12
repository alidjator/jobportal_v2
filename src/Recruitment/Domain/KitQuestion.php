<?php

namespace Src\Recruitment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A question of an interview kit, and of the copy an interview keeps. The weight tells how much the answer counts in a scorecard's result; the guidance is
 * for the interviewers only. The position is the place in the list.
 */
final readonly class KitQuestion
{
    public const int TEXT_MAX = 500;

    public const int GUIDANCE_MAX = 1000;

    public const int WEIGHT_MIN = 1;

    public const int WEIGHT_MAX = 5;

    public const int MAX_PER_LIST = 20;

    public function __construct(public string $text, public ?string $guidance, public int $weight) {}

    /**
     * The questions of a list, trimmed, with the messages of every rule they break (by field, for example `questions.2.text`).
     *
     * @param  list<array{text: string, guidance: ?string, weight: int}>  $raw
     * @return array{0: list<self>, 1: array<string, list<string>>}
     */
    public static function check(array $raw, int $min): array
    {
        $errors = [];
        $questions = [];

        if (count($raw) < $min || count($raw) > self::MAX_PER_LIST) {
            $errors['questions'] = [$min > 0 ? "Give between {$min} and ".self::MAX_PER_LIST.' questions.' : 'Give at most '.self::MAX_PER_LIST.' questions.'];
        }

        foreach ($raw as $index => $item) {
            $text = trim($item['text']);
            $guidance = isset($item['guidance']) ? trim($item['guidance']) : '';

            if ($text === '') {
                $errors["questions.{$index}.text"] = ['Write the question.'];
            } elseif (mb_strlen($text) > self::TEXT_MAX) {
                $errors["questions.{$index}.text"] = ['A question can have at most '.self::TEXT_MAX.' characters.'];
            }

            if (mb_strlen($guidance) > self::GUIDANCE_MAX) {
                $errors["questions.{$index}.guidance"] = ['The guidance can have at most '.self::GUIDANCE_MAX.' characters.'];
            }

            if ($item['weight'] < self::WEIGHT_MIN || $item['weight'] > self::WEIGHT_MAX) {
                $errors["questions.{$index}.weight"] = ['The weight must be between '.self::WEIGHT_MIN.' and '.self::WEIGHT_MAX.'.'];
            }

            $questions[] = new self($text, $guidance === '' ? null : $guidance, $item['weight']);
        }

        return [$questions, $errors];
    }

    /**
     * @param  list<array{text: string, guidance: ?string, weight: int}>  $raw
     * @return list<self>
     *
     * @throws BusinessRuleViolation `invalid-kit`
     */
    public static function many(array $raw, int $min): array
    {
        [$questions, $errors] = self::check($raw, $min);

        if ($errors !== []) {
            throw self::violation($errors);
        }

        return $questions;
    }

    /** @param  array<string, list<string>>  $errors */
    public static function violation(array $errors): BusinessRuleViolation
    {
        return new BusinessRuleViolation((string) reset($errors)[0], 'invalid-kit', extensions: ['errors' => $errors]);
    }
}
