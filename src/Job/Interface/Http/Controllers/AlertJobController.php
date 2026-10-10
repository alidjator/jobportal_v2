<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Job\Application\Browse\SearchJobsOfAlert;
use Src\Job\Interface\Http\Requests\ListAlertJobsRequest;
use Src\Job\Interface\Http\Resources\PublicJobListResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The live jobs that one of the job seeker's own alerts matches right now, in the shape of the public search. */
final class AlertJobController extends ApiController
{
    public function index(ListAlertJobsRequest $request, int $id, ActorResolver $actors, RequestContext $context, SearchJobsOfAlert $search): AnonymousResourceCollection
    {
        $page = $search->execute($actors->fromRequest($request), $id, $request->pageRequest(), $request->ip(), $context->language);

        return PublicJobListResource::collection(PaginatedResources::paginator($page, $request));
    }
}
