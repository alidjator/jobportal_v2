<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Domain\KitQuestion;

/**
 * An interview kit with its questions in order. `guidance` is for the interviewers only.
 *
 * @property InterviewKit $resource
 */
final class InterviewKitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...InterviewKitSummaryResource::fields($this->resource),
            'questions' => self::questions($this->resource->questions),
        ];
    }

    /**
     * @param  list<KitQuestion>  $questions
     * @return list<array{position: int, text: string, guidance: ?string, weight: int, anchors: array{score_1: ?string, score_3: ?string, score_5: ?string}}>
     */
    public static function questions(array $questions): array
    {
        return array_map(
            static fn (KitQuestion $question, int $index): array => ['position' => $index + 1, ...self::fields($question)],
            $questions,
            array_keys($questions),
        );
    }

    /** @return array{text: string, guidance: ?string, weight: int, anchors: array{score_1: ?string, score_3: ?string, score_5: ?string}} */
    public static function fields(KitQuestion $question): array
    {
        return [
            'text' => $question->text,
            'guidance' => $question->guidance,
            'weight' => $question->weight,
            'anchors' => ['score_1' => $question->anchor1, 'score_3' => $question->anchor3, 'score_5' => $question->anchor5],
        ];
    }
}
