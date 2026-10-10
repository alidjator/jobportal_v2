<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Job\Application\Saved\ListSavedJobs;
use Src\Job\Application\Saved\SaveJob;
use Src\Job\Application\Saved\UnsaveJob;
use Src\Job\Interface\Http\Requests\ListSavedJobsRequest;
use Src\Job\Interface\Http\Resources\SavedJobResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The jobs the signed-in job seeker saved. */
final class SavedJobController extends ApiController
{
    public function index(ListSavedJobsRequest $request, RequestContext $context, ActorResolver $actors, ListSavedJobs $list): AnonymousResourceCollection
    {
        return SavedJobResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $request->pageRequest(), $context->language), $request));
    }

    /** Saves the job; saving it again is not an error. */
    public function save(Request $request, int $id, ActorResolver $actors, SaveJob $save): Response
    {
        $save->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    /** Removes the job from the saved jobs; removing one that is not saved is not an error. */
    public function remove(Request $request, int $id, ActorResolver $actors, UnsaveJob $unsave): Response
    {
        $unsave->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
