<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Job\Application\Browse\GetPublicJob;
use Src\Job\Application\Browse\SearchJobs;
use Src\Job\Interface\Http\Requests\SearchJobsRequest;
use Src\Job\Interface\Http\Resources\PublicJobListResource;
use Src\Job\Interface\Http\Resources\PublicJobResource;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\ActorProvider;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/**
 * The public side of jobs: no sign-in is needed. A job seeker who sends a token anyway also gets the match with their profile (an invalid token is ignored). The search lists live jobs; the page shows one. `{job}` is the id, optionally followed by the cosmetic slug (`123` or
 * `123-backend-developer`); the slug is ignored.
 */
final class PublicJobController extends ApiController
{
    public function index(SearchJobsRequest $request, RequestContext $context, SearchJobs $search): AnonymousResourceCollection
    {
        $page = $search->execute($request->criteria(), $request->pageRequest(), $request->ip(), $context->language, $this->jobSeekerId($request));

        return PaginatedResources::of($page, PublicJobListResource::class, $request);
    }

    public function show(Request $request, string $job, RequestContext $context, GetPublicJob $get): PublicJobResource
    {
        return new PublicJobResource($get->execute((int) $job, $context->language, $this->jobSeekerId($request)));
    }

    /** The job seeker whose token the request carries, or null (no token, a bad one, or another kind of account). */
    private function jobSeekerId(Request $request): ?int
    {
        $account = $request->bearerToken() === null ? null : $request->user('user');

        if (! $account instanceof ActorProvider) {
            return null;
        }

        $actor = $account->toActor();

        return $actor->kind === ActorKind::JobSeeker ? $actor->id : null;
    }
}
