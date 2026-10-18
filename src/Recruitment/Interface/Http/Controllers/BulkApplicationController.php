<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Recruitment\Application\BulkItemResult;
use Src\Recruitment\Application\BulkProcessApplications;
use Src\Recruitment\Application\FillJob;
use Src\Recruitment\Interface\Http\Requests\BulkMoveApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\BulkRejectApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\FillJobRequest;
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

    public function reject(BulkRejectApplicationsRequest $request, int $id, ActorResolver $actors, BulkProcessApplications $bulk): AnonymousResourceCollection
    {
        $results = $bulk->reject($actors->fromRequest($request), $id, $request->applicationIds(), $request->reason(), $request->note());

        return BulkItemResultResource::collection($results);
    }

    /** "Mark as filled": closes the job when asked and rejects the chosen applications with the reason `position_filled`; `meta.job_status` is the job's status afterwards. */
    public function fill(FillJobRequest $request, int $id, ActorResolver $actors, FillJob $fill): AnonymousResourceCollection
    {
        $result = $fill->execute($actors->fromRequest($request), $id, $request->close(), $request->applicationIds(), $request->note(), $request->ip(), $request->userAgent());

        return FillJobItemResource::collection($result->items)->additional(['meta' => ['job_status' => $result->jobStatus]]);
    }
}
