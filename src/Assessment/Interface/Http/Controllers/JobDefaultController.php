<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Defaults\ListJobAssessors;
use Src\Assessment\Application\Defaults\ListJobDefaults;
use Src\Assessment\Application\Defaults\RemoveJobDefault;
use Src\Assessment\Application\Defaults\SetJobDefault;
use Src\Assessment\Interface\Http\Requests\SetJobDefaultRequest;
use Src\Assessment\Interface\Http\Resources\AssessorResource;
use Src\Assessment\Interface\Http\Resources\JobDefaultResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The default test of a job per assessment stage, set in the job editor: a move into the stage sends it, and its advance rule moves the application on by itself. */
final class JobDefaultController extends ApiController
{
    public function index(Request $request, int $id, ActorResolver $actors, ListJobDefaults $list): AnonymousResourceCollection
    {
        return JobDefaultResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    /** The members who may be chosen as assessors of a test on this job (`assessors`, at most 5 per test). */
    public function assessors(Request $request, int $id, ActorResolver $actors, ListJobAssessors $list): AnonymousResourceCollection
    {
        return AssessorResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    public function put(SetJobDefaultRequest $request, int $id, int $stageId, ActorResolver $actors, SetJobDefault $set): JobDefaultResource
    {
        return new JobDefaultResource($set->execute(
            $actors->fromRequest($request), $id, $stageId, $request->testId(), $request->dueDays(), $request->assessorIds(), $request->advanceMinPercentage(), $request->advanceToStageId(),
        ));
    }

    public function destroy(Request $request, int $id, int $stageId, ActorResolver $actors, RemoveJobDefault $remove): Response
    {
        $remove->execute($actors->fromRequest($request), $id, $stageId);

        return response()->noContent();
    }
}
