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
            'questions.*' => ['array:text,guidance,weight,anchor_1,anchor_3,anchor_5'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.guidance' => ['nullable', 'string'],
            'questions.*.weight' => ['required', 'integer'],
            'questions.*.anchor_1' => ['nullable', 'string'],
            'questions.*.anchor_3' => ['nullable', 'string'],
            'questions.*.anchor_5' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return list<array{text: string, guidance: ?string, weight: int, anchor_1?: ?string, anchor_3?: ?string, anchor_5?: ?string}>
     */
    public static function questions(array $questions): array
    {
        return array_values(array_map(static fn (array $question): array => [
            'text' => (string) $question['text'],
            'guidance' => isset($question['guidance']) ? (string) $question['guidance'] : null,
            'weight' => (int) $question['weight'],
            'anchor_1' => isset($question['anchor_1']) ? (string) $question['anchor_1'] : null,
            'anchor_3' => isset($question['anchor_3']) ? (string) $question['anchor_3'] : null,
            'anchor_5' => isset($question['anchor_5']) ? (string) $question['anchor_5'] : null,
        ], $questions));
    }
}
