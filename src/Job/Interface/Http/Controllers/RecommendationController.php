<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Job\Application\Matching\RecommendJobs;
use Src\Job\Interface\Http\Resources\RecommendedJobResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The live jobs that fit the signed-in job seeker best, with the match of each. */
final class RecommendationController extends ApiController
{
    public function index(Request $request, RequestContext $context, ActorResolver $actors, RecommendJobs $recommend): AnonymousResourceCollection
    {
        return RecommendedJobResource::collection($recommend->execute($actors->fromRequest($request), $context->language));
    }
}
