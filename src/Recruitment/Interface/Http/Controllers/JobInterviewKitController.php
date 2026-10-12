<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\ListJobInterviewKits;
use Src\Recruitment\Application\RemoveJobInterviewKit;
use Src\Recruitment\Application\SetJobInterviewKit;
use Src\Recruitment\Interface\Http\Requests\SetJobInterviewKitRequest;
use Src\Recruitment\Interface\Http\Resources\JobInterviewKitResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The default kit of a job per interview stage, for the members who may see the job. */
final class JobInterviewKitController extends ApiController
{
    public function index(Request $request, int $id, ActorResolver $actors, ListJobInterviewKits $list): AnonymousResourceCollection
    {
        return JobInterviewKitResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    public function update(SetJobInterviewKitRequest $request, int $id, int $stageId, ActorResolver $actors, SetJobInterviewKit $set): JobInterviewKitResource
    {
        return new JobInterviewKitResource($set->execute($actors->fromRequest($request), $id, $stageId, $request->kitId()));
    }

    public function destroy(Request $request, int $id, int $stageId, ActorResolver $actors, RemoveJobInterviewKit $remove): Response
    {
        $remove->execute($actors->fromRequest($request), $id, $stageId);

        return response()->noContent();
    }
}
