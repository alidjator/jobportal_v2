<?php

namespace Src\Recruitment\Interface\Http\Requests;

/**
 * The shape of a list of kit questions. The length limits, the weight range and the number of questions are domain rules (422 `invalid-kit`), so the
 * client gets one error per field in the same format as every other business rule.
 */
final class KitQuestionFields
{
    /** @return array<string, list<mixed>> */
    public static function rules(bool $mayBeEmpty): array
    {
        return [
            'questions' => [$mayBeEmpty ? 'present' : 'required', 'array', 'max:100'],
            'questions.*' => ['array:text,guidance,weight'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.guidance' => ['nullable', 'string'],
            'questions.*.weight' => ['required', 'integer'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return list<array{text: string, guidance: ?string, weight: int}>
     */
    public static function questions(array $questions): array
    {
        return array_values(array_map(static fn (array $question): array => [
            'text' => (string) $question['text'],
            'guidance' => isset($question['guidance']) ? (string) $question['guidance'] : null,
            'weight' => (int) $question['weight'],
        ], $questions));
    }
}
