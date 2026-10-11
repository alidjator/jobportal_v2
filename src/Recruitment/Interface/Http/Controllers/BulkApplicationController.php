<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Recruitment\Application\BulkProcessApplications;
use Src\Recruitment\Interface\Http\Requests\BulkMoveApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\BulkRejectApplicationsRequest;
use Src\Recruitment\Interface\Http\Resources\BulkItemResultResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Moves or rejects several applications of one job; the answer says what happened to each, in the order of the selection. */
final class BulkApplicationController extends ApiController
{
    public function move(BulkMoveApplicationsRequest $request, int $id, ActorResolver $actors, BulkProcessApplications $bulk): AnonymousResourceCollection
    {
        $results = $bulk->move($actors->fromRequest($request), $id, $request->applicationIds(), $request->stageId(), $request->interview(), $request->offer(), $request->assessment());

        return BulkItemResultResource::collection($results);
    }

    public function reject(BulkRejectApplicationsRequest $request, int $id, ActorResolver $actors, BulkProcessApplications $bulk): AnonymousResourceCollection
    {
        $results = $bulk->reject($actors->fromRequest($request), $id, $request->applicationIds(), $request->reason(), $request->note());

        return BulkItemResultResource::collection($results);
    }
}
