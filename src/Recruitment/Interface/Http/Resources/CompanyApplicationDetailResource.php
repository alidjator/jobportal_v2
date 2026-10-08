<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\ApplicationAnswerView;
use Src\Recruitment\Application\CompanyApplicationView;
use Src\Recruitment\Application\RecruiterHistoryEntry;

/**
 * The detail of an application for the company's team: the whole profile snapshot, the cover letter, the answers and the stage history with the company's
 * stage names (a stage that was deleted later has none) and the rejection reason and note. For an applicant whose account was anonymised the applicant holds
 * only `is_deleted` and a null name.
 *
 * @property CompanyApplicationView $resource
 */
final class CompanyApplicationDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $data = $view->data;

        return [
            'id' => $data->id,
            'job' => ['id' => $data->jobId, 'title' => $view->jobTitle],
            'stage' => ['id' => $data->stageId, 'type' => $data->stageType->value, 'name' => $view->stageName],
            'meets_must_have' => $data->meetsMustHave,
            'applied_at' => $data->appliedAt->format(DATE_ATOM),
            'rating' => (new ApplicationRatingResource($view->rating))->resolve($request),
            'applicant' => $data->snapshot === null
                ? ['is_deleted' => true, 'name' => null]
                : ['is_deleted' => false, ...array_diff_key($data->snapshot, ['version' => true])],
            'cover_letter' => $data->coverLetter,
            'resume' => $data->resumeName === null ? null : ['name' => $data->resumeName],
            'withdrawn_note' => $data->withdrawnNote,
            'answers' => array_map(static fn (ApplicationAnswerView $answer): array => [
                'question' => ['id' => $answer->questionId, 'text' => $answer->questionText, 'type' => $answer->type->value],
                'text' => $answer->text,
                'number' => $answer->number,
                'bool' => $answer->bool,
                'options' => $answer->options,
            ], $data->answers),
            'history' => array_map(fn (RecruiterHistoryEntry $entry): array => [
                'from' => $entry->fromType === null ? null : ['stage_id' => $entry->fromStageId, 'type' => $entry->fromType->value, 'name' => $view->stageNames[(int) $entry->fromStageId] ?? null],
                'to' => ['stage_id' => $entry->toStageId, 'type' => $entry->toType->value, 'name' => $view->stageNames[$entry->toStageId] ?? null],
                'by' => $entry->by->value,
                'actor' => $entry->actorId !== null && isset($view->actorNames[$entry->actorId]) ? ['id' => $entry->actorId, 'name' => $view->actorNames[$entry->actorId]] : null,
                'reason' => $entry->reason?->value,
                'note' => $entry->note,
                'at' => $entry->at->format(DATE_ATOM),
            ], $data->history),
        ];
    }
}
