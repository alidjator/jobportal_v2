<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\ReviewAnswerView;
use Src\Recruitment\Application\ReviewItemView;
use Src\Recruitment\Application\ReviewRequestView;

/**
 * A shortlist review request. `status` is `open`, `complete` (every reviewer answered every active application) or `closed`; `overdue` is true for an open request after its due day
 * (Jakarta time). `progress` counts the answers given and awaited over the active applications. `pending_for_me` is the number of active applications the caller still has to
 * answer, null when they are not a reviewer. In an item, `active` is false once the application became final (nothing is awaited), `locked` is true while the caller, a reviewer,
 * has not answered it (the others' decisions and reasons stay hidden; `answered` still tells who did), and `card` is the compact card of the candidate, only on the review page.
 *
 * @property ReviewRequestView $resource
 */
final class ReviewRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $review = $view->request;

        return [
            'id' => $review->id,
            'job' => ['id' => $review->jobId, 'title' => $view->jobTitle],
            'requested_by' => ['id' => $review->requestedBy, 'name' => $view->requesterName],
            'message' => $review->message,
            'due_on' => $review->dueOn->format('Y-m-d'),
            /** @var 'open'|'complete'|'closed' */
            'status' => $review->status->value,
            'overdue' => $view->overdue,
            'created_at' => $review->createdAt->format(DATE_ATOM),
            'reviewers' => $view->reviewers,
            'progress' => $view->progress,
            'pending_for_me' => $view->pendingForMe,
            'items' => array_map(fn (ReviewItemView $item): array => [
                'application_id' => $item->applicationId,
                'candidate_name' => $item->candidateName,
                'stage' => ['id' => $item->stageId, 'type' => $item->stageType->value, 'name' => $item->stageName],
                'active' => $item->active,
                'locked' => $item->locked,
                /** @var list<array{reviewer: array{id: int, name: string|null}, mine: bool, answered: bool, decision: 'advance'|'decline'|'maybe'|null, reason: string|null, answered_at: string|null}> */
                'answers' => array_map(static fn (ReviewAnswerView $answer): array => [
                    'reviewer' => ['id' => $answer->reviewerId, 'name' => $answer->reviewerName],
                    'mine' => $answer->mine,
                    'answered' => $answer->answered,
                    'decision' => $answer->decision,
                    'reason' => $answer->reason,
                    'answered_at' => $answer->answeredAt?->format(DATE_ATOM),
                ], $item->answers),
                /** @var array<string, mixed>|null */
                'card' => $item->card,
            ], $view->items),
        ];
    }
}
