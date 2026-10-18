<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\ApplicationAnswerView;
use Src\Recruitment\Application\ApplicationInterviewResult;
use Src\Recruitment\Application\ApplicationReviewEntry;
use Src\Recruitment\Application\CompanyApplicationView;
use Src\Recruitment\Application\OtherApplicationView;
use Src\Recruitment\Application\RecruiterHistoryEntry;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;

/**
 * The detail of an application for the company's team: the whole profile snapshot, the cover letter, the answers and the stage history with the company's
 * stage names (a stage that was deleted later has none) and the rejection reason and note. A withdrawn application also carries `withdrawal`: the reason, who marked it and the team's own note. For an applicant whose account was anonymised the applicant holds
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
            'stage_entered_at' => $data->stageEnteredAt->format(DATE_ATOM),
            'days_in_stage' => $view->daysInStage,
            'stale' => $view->stale,
            /** @var array{join_date: string, note: string|null}|null the join date and the note for the candidate of a hired application */
            'hire' => HirePayload::of($data->hire),
            /** @var list<array{id: int, job: array{id: int, title: string|null}, stage: array{type: string, name: string}, applied_at: string}> */
            'other_applications' => array_map(static fn (OtherApplicationView $other): array => [
                'id' => $other->application->id,
                'job' => ['id' => $other->application->jobId, 'title' => $other->jobTitle],
                'stage' => ['type' => $other->application->stageType->value, 'name' => $other->application->stageName],
                'applied_at' => $other->application->appliedAt->format(DATE_ATOM),
            ], $view->otherApplications),
            'others_hidden' => $view->othersHidden,
            /** @var list<array{request: array{id: int, due_on: string, status: string}, reviewer: array{id: int, name: string|null}, decision: 'advance'|'decline'|'maybe', reason: string|null, answered_at: string}> the answers of shortlist reviews about this application; a reviewer sees the others' only after sending their own */
            'reviews' => array_map(static fn (ApplicationReviewEntry $entry): array => [
                'request' => ['id' => $entry->requestId, 'due_on' => $entry->dueOn->format('Y-m-d'), 'status' => $entry->requestStatus->value],
                'reviewer' => ['id' => $entry->reviewerId, 'name' => $entry->reviewerName],
                'decision' => $entry->decision->value,
                'reason' => $entry->reason,
                'answered_at' => $entry->answeredAt->format(DATE_ATOM),
            ], $view->reviews),
            /** @var array{average: float|null, count: int, mine: int|null, members: list<array{user_id: int, name: string, stars: int}>} */
            'rating' => (new ApplicationRatingResource($view->rating))->resolve($request),
            /** @var list<array{id: int, status: string, starts_at: string, format: string, result: array{score: float|null, scorecards_sent: int, panel_size: int, recommendations: array<string, int>}|null}> */
            'interviews' => array_map(static fn (ApplicationInterviewResult $item): array => [
                'id' => $item->interview->id,
                'status' => $item->interview->status->value,
                'starts_at' => $item->interview->startsAt->format(DATE_ATOM),
                'format' => $item->interview->format->value,
                'result' => $item->result === null ? null : [
                    'score' => $item->result->score,
                    'scorecards_sent' => $item->result->scorecardsSent,
                    'panel_size' => $item->panelSize,
                    'recommendations' => $item->result->recommendations,
                ],
            ], $view->interviews),
            'applicant' => $data->snapshot === null
                ? ['is_deleted' => true, 'name' => null]
                : ['is_deleted' => false, 'contact_hidden' => $data->contactHidden, ...array_diff_key($data->snapshot, ['version' => true])],
            'cover_letter' => $data->coverLetter,
            'resume' => $data->resumeName === null ? null : ['name' => $data->resumeName],
            'withdrawn_note' => $data->withdrawnNote,
            /** @var array{reason: string|null, by: string, actor: array{id: int, name: string}|null, note: string|null, at: string}|null */
            'withdrawal' => $this->withdrawal($view),
            /** @var list<array{question: array{id: int, text: string, type: string}, text: string|null, number: int|null, bool: bool|null, options: list<array{id: int, label: string}>}> */
            'answers' => array_map(static fn (ApplicationAnswerView $answer): array => [
                'question' => ['id' => $answer->questionId, 'text' => $answer->questionText, 'type' => $answer->type->value],
                'text' => $answer->text,
                'number' => $answer->number,
                'bool' => $answer->bool,
                'options' => $answer->options,
            ], $data->answers),
            /** @var list<array{from: array{stage_id: int|null, type: string, name: string|null}|null, to: array{stage_id: int, type: string, name: string|null}, by: string, actor: array{id: int, name: string}|null, reason: string|null, withdrawal_reason: string|null, note: string|null, at: string}> */
            'history' => array_map(fn (RecruiterHistoryEntry $entry): array => [
                'from' => $entry->fromType === null ? null : ['stage_id' => $entry->fromStageId, 'type' => $entry->fromType->value, 'name' => $view->stageNames[(int) $entry->fromStageId] ?? null],
                'to' => ['stage_id' => $entry->toStageId, 'type' => $entry->toType->value, 'name' => $view->stageNames[$entry->toStageId] ?? null],
                'by' => $entry->by->value,
                'actor' => $entry->actorId !== null && isset($view->actorNames[$entry->actorId]) ? ['id' => $entry->actorId, 'name' => $view->actorNames[$entry->actorId]] : null,
                'reason' => $entry->reason?->value,
                'withdrawal_reason' => $entry->withdrawalReason?->value,
                'note' => $entry->note,
                'at' => $entry->at->format(DATE_ATOM),
            ], $data->history),
        ];
    }

    /** The last move into `withdrawn`, when the application is withdrawn now. The team's note is the one of the history entry; the applicant's is the application's. */
    private function withdrawal(CompanyApplicationView $view): ?array
    {
        $data = $view->data;

        if ($data->stageType !== StageType::Withdrawn) {
            return null;
        }

        foreach (array_reverse($data->history) as $entry) {
            if ($entry->toType === StageType::Withdrawn) {
                $byTeam = $entry->by === StageMoveActor::Recruiter;

                return [
                    'reason' => $entry->withdrawalReason?->value,
                    'by' => $byTeam ? 'recruiter' : 'applicant',
                    'actor' => $byTeam && $entry->actorId !== null && isset($view->actorNames[$entry->actorId]) ? ['id' => $entry->actorId, 'name' => $view->actorNames[$entry->actorId]] : null,
                    'note' => $byTeam ? $entry->note : $data->withdrawnNote,
                    'at' => $entry->at->format(DATE_ATOM),
                ];
            }
        }

        return null;
    }
}
