<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\ApplicationAnswerView;
use Src\Recruitment\Application\ApplicationHistoryEntry;
use Src\Recruitment\Application\ApplicationView;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;

/**
 * The applicant's own detail of an application. The must-have result, the profile snapshot, the recruiter's notes and the free note of a rejection are not part of it; the reason code of a rejection is.
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
        $rejection = $row->status === StageType::Rejected ? $this->rejectionReason($data->history) : null;

        return [
            'id' => $row->id,
            'job' => ['id' => $row->jobId, 'title' => $this->resource->jobTitle],
            'company' => ['id' => $row->companyId, 'name' => $this->resource->companyName],
            'status' => $row->status->value,
            'applied_at' => $row->appliedAt->format(DATE_ATOM),
            'viewed_at' => $row->viewedAt?->format(DATE_ATOM),
            'rejection_reason' => $rejection?->value,
            'cover_letter' => $data->coverLetter,
            'resume' => $data->resumeName === null ? null : ['name' => $data->resumeName],
            'withdrawn_note' => $data->withdrawnNote,
            /** @var array{join_date: string, note: string|null}|null the join date and the company's note once hired */
            'hire' => HirePayload::of($data->hire),
            /** Whether the company may keep the applicant's profile in its talent pool for other jobs; `PUT`/`DELETE me/applications/{id}/talent-pool-consent` changes it. */
            'talent_pool_consent' => $data->talentPoolConsent,
            /** @var array{reason: string|null, by: string, at: string}|null */
            'withdrawal' => $row->status === StageType::Withdrawn ? $this->withdrawal($data->history) : null,
            /** @var list<array{status: string, at: string, by: string}> */
            'history' => array_map(static fn (ApplicationHistoryEntry $entry): array => [
                'status' => $entry->status->value,
                'at' => $entry->at->format(DATE_ATOM),
                'by' => $entry->by->value,
            ], $data->history),
            /** @var list<array{question: array{id: int, text: string, type: string}, text: string|null, number: int|null, bool: bool|null, options: list<array{id: int, label: string}>}> */
            'answers' => array_map(static fn (ApplicationAnswerView $answer): array => [
                'question' => ['id' => $answer->questionId, 'text' => $answer->questionText, 'type' => $answer->type->value],
                'text' => $answer->text,
                'number' => $answer->number,
                'bool' => $answer->bool,
                'options' => $answer->options,
            ], $data->answers),
        ];
    }

    /** @param list<ApplicationHistoryEntry> $history */
    private function rejectionReason(array $history): ?RejectionReason
    {
        foreach (array_reverse($history) as $entry) {
            if ($entry->status === StageType::Rejected) {
                return $entry->reason;
            }
        }

        return null;
    }

    /**
     * The last move into `withdrawn`: its reason (the applicant's own or the team's), who marked it and when. The team's note is never part of it.
     *
     * @param  list<ApplicationHistoryEntry>  $history
     * @return array{reason: string|null, by: string, at: string}|null
     */
    private function withdrawal(array $history): ?array
    {
        foreach (array_reverse($history) as $entry) {
            if ($entry->status === StageType::Withdrawn) {
                return [
                    'reason' => $entry->withdrawalReason?->value,
                    'by' => $entry->by === StageMoveActor::Recruiter ? 'recruiter' : 'applicant',
                    'at' => $entry->at->format(DATE_ATOM),
                ];
            }
        }

        return null;
    }
}
