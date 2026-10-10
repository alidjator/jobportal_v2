<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Job\Application\Browse\GetPublicJob;
use Src\Job\Application\Browse\SearchJobs;
use Src\Job\Application\Views\TrackJobView;
use Src\Job\Interface\Http\Requests\SearchJobsRequest;
use Src\Job\Interface\Http\Resources\PublicJobListResource;
use Src\Job\Interface\Http\Resources\PublicJobResource;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\ActorProvider;
use Src\Shared\Application\Company\CompanyMembershipResolver;
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

        return PublicJobListResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, string $job, RequestContext $context, GetPublicJob $get): PublicJobResource
    {
        return new PublicJobResource($get->execute((int) $job, $context->language, $this->jobSeekerId($request)));
    }

    /** Counts one view of a live job; the client calls it when a person looks at the page. Nothing is returned. */
    public function view(Request $request, string $job, TrackJobView $track, CompanyMembershipResolver $memberships): Response
    {
        $track->execute((int) $job, $this->viewer($request, $memberships), (string) $request->ip(), (string) $request->userAgent());

        return response()->noContent();
    }

    /** The signed-in account the request carries (a company member with their company), or null (no token, a bad one, or an admin). */
    private function viewer(Request $request, CompanyMembershipResolver $memberships): ?Actor
    {
        $account = $request->bearerToken() === null ? null : $request->user('user');

        if (! $account instanceof ActorProvider) {
            return null;
        }

        $actor = $account->toActor();

        return $actor->kind === ActorKind::CompanyMember && $actor->id !== null ? $actor->withMembership($memberships->forUser($actor->id)) : $actor;
    }

    /** The job seeker whose token the request carries, or null. */
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
