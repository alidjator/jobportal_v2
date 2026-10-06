<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Job\Application\Browse\GetPublicJob;
use Src\Job\Application\Browse\SearchJobs;
use Src\Job\Interface\Http\Requests\SearchJobsRequest;
use Src\Job\Interface\Http\Resources\PublicJobListResource;
use Src\Job\Interface\Http\Resources\PublicJobResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/**
 * The public side of jobs: no sign-in. The search lists live jobs; the page shows one. `{job}` is the id, optionally followed by the cosmetic slug (`123` or
 * `123-backend-developer`); the slug is ignored.
 */
final class PublicJobController extends ApiController
{
    public function index(SearchJobsRequest $request, RequestContext $context, SearchJobs $search): AnonymousResourceCollection
    {
        $page = $search->execute($request->criteria(), $request->pageRequest(), $request->ip(), $context->language);

        return PaginatedResources::of($page, PublicJobListResource::class, $request);
    }

    public function show(string $job, RequestContext $context, GetPublicJob $get): PublicJobResource
    {
        return new PublicJobResource($get->execute((int) $job, $context->language));
    }
}
