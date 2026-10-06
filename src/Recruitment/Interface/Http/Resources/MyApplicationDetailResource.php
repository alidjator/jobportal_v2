<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\ApplicationAnswerView;
use Src\Recruitment\Application\ApplicationHistoryEntry;
use Src\Recruitment\Application\ApplicationView;

/**
 * The applicant's own detail of an application. The must-have result, the profile snapshot, the recruiter's notes and the rejection reasons are not part of it.
 *
 * @property ApplicationView $resource
 */
final class MyApplicationDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = $this->resource->data;
        $row = $data->row;

        return [
            'id' => $row->id,
            'job' => ['id' => $row->jobId, 'title' => $this->resource->jobTitle],
            'company' => ['id' => $row->companyId, 'name' => $this->resource->companyName],
            'status' => $row->status->value,
            'applied_at' => $row->appliedAt->format(DATE_ATOM),
            'cover_letter' => $data->coverLetter,
            'resume' => $data->resumeName === null ? null : ['name' => $data->resumeName],
            'withdrawn_note' => $data->withdrawnNote,
            'history' => array_map(static fn (ApplicationHistoryEntry $entry): array => [
                'status' => $entry->status->value,
                'at' => $entry->at->format(DATE_ATOM),
                'by' => $entry->by->value,
            ], $data->history),
            'answers' => array_map(static fn (ApplicationAnswerView $answer): array => [
                'question' => ['id' => $answer->questionId, 'text' => $answer->questionText, 'type' => $answer->type->value],
                'text' => $answer->text,
                'number' => $answer->number,
                'bool' => $answer->bool,
                'options' => $answer->options,
            ], $data->answers),
        ];
    }
}
