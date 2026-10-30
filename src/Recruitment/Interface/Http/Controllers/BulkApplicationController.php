<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Recruitment\Application\BulkItemResult;
use Src\Recruitment\Application\BulkProcessApplications;
use Src\Recruitment\Application\FillJob;
use Src\Recruitment\Application\RejectRemainingApplications;
use Src\Recruitment\Interface\Http\Requests\BulkMoveApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\BulkRejectApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\FillJobRequest;
use Src\Recruitment\Interface\Http\Requests\RejectRemainingRequest;
use Src\Recruitment\Interface\Http\Resources\BulkItemResultResource;
use Src\Recruitment\Interface\Http\Resources\FillJobItemResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Moves or rejects several applications of one job, or marks the job filled; the answer says what happened to each, in the order of the selection. */
final class BulkApplicationController extends ApiController
{
    public function move(BulkMoveApplicationsRequest $request, int $id, ActorResolver $actors, BulkProcessApplications $bulk): AnonymousResourceCollection
    {
        $results = $bulk->move($actors->fromRequest($request), $id, $request->applicationIds(), $request->stageId(), $request->interview(), $request->offer(), $request->assessment(), $request->scheduleLater(), $request->hire());

        return BulkItemResultResource::collection($results)->additional(['meta' => ['job_filled' => array_filter($results, static fn (BulkItemResult $result): bool => $result->jobFilled) !== []]]);
    }

    /**
     * Rejects the chosen applications. When the company has an undo window the answer is 202 and the rejections wait: `meta.commit_at` says until when and `meta.batch_id` undoes all of them at
     * once (`DELETE company/rejection-batches/{batchId}`); without a window it is 200 and they took effect.
     */
    public function reject(BulkRejectApplicationsRequest $request, int $id, ActorResolver $actors, BulkProcessApplications $bulk): AnonymousResourceCollection|JsonResponse
    {
        $results = $bulk->reject($actors->fromRequest($request), $id, $request->applicationIds(), $request->reason(), $request->note());

        return $this->withPending(BulkItemResultResource::collection($results), $results);
    }

    /** "Mark as filled": closes the job when asked and rejects the chosen applications with the reason `position_filled`; `meta.job_status` is the job's status afterwards. A company with an undo window answers 202 with `meta.commit_at` and `meta.batch_id`, as the bulk reject does. */
    public function fill(FillJobRequest $request, int $id, ActorResolver $actors, FillJob $fill): AnonymousResourceCollection|JsonResponse
    {
        $result = $fill->execute($actors->fromRequest($request), $id, $request->close(), $request->applicationIds(), $request->note(), $request->ip(), $request->userAgent());

        return $this->withPending(FillJobItemResource::collection($result->items)->additional(['meta' => ['job_status' => $result->jobStatus]]), $result->items);
    }

    /**
     * "Reject the remaining" applications of a closed or expired job, in the stages chosen. The answer is 202 `{count}`: a queued step rejects them and tells the member how many were rejected and
     * skipped (the in-app notice `applications.reject_remaining_done`). 409 `job-still-open` while the job is live.
     */
    public function rejectRemaining(RejectRemainingRequest $request, int $id, ActorResolver $actors, RejectRemainingApplications $reject): JsonResponse
    {
        $count = $reject->request($actors->fromRequest($request), $id, $request->stageIds(), $request->reason(), $request->note());

        return response()->json(['data' => ['count' => $count]], JsonResponse::HTTP_ACCEPTED);
    }

    /**
     * 202 with the end of the undo window when any item waits it, the plain answer otherwise.
     *
     * @param  list<BulkItemResult>  $items
     */
    private function withPending(AnonymousResourceCollection $collection, array $items): AnonymousResourceCollection|JsonResponse
    {
        $pending = array_values(array_filter($items, static fn (BulkItemResult $item): bool => $item->isPending()));

        if ($pending === []) {
            return $collection;
        }

        return $collection
            ->additional(['meta' => [...($collection->additional['meta'] ?? []), 'commit_at' => $pending[0]->commitAt?->format(DATE_ATOM), 'batch_id' => $pending[0]->batchId]])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_ACCEPTED);
    }
}
