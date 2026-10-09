<?php

namespace Src\JobSeeker\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\JobSeeker\Application\Candidates\GetCandidate;
use Src\JobSeeker\Application\Candidates\GetCandidateAvatar;
use Src\JobSeeker\Application\Candidates\GetCandidateResume;
use Src\JobSeeker\Application\Candidates\ListMyProfileViews;
use Src\JobSeeker\Application\Candidates\SearchCandidates;
use Src\JobSeeker\Interface\Http\Requests\ListProfileViewsRequest;
use Src\JobSeeker\Interface\Http\Requests\SearchCandidatesRequest;
use Src\JobSeeker\Interface\Http\Resources\CandidateListResource;
use Src\JobSeeker\Interface\Http\Resources\CandidateResource;
use Src\JobSeeker\Interface\Http\Resources\ProfileViewResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The candidate search of the company side, and the list of companies that looked at the job seeker's own profile. */
final class CandidateController extends ApiController
{
    public function index(SearchCandidatesRequest $request, RequestContext $context, ActorResolver $actors, SearchCandidates $search): AnonymousResourceCollection
    {
        $page = $search->execute($actors->fromRequest($request), $request->toQuery(), $request->pageRequest(), $context->language);

        return PaginatedResources::of($page, CandidateListResource::class, $request);
    }

    public function show(Request $request, string $slug, RequestContext $context, ActorResolver $actors, GetCandidate $get): CandidateResource
    {
        return new CandidateResource($get->execute($actors->fromRequest($request), $slug, $context->language));
    }

    public function avatar(Request $request, string $slug, ActorResolver $actors, GetCandidateAvatar $get): Response
    {
        $avatar = $get->execute($actors->fromRequest($request), $slug);

        return new Response($avatar->contents, Response::HTTP_OK, [
            'Content-Type' => $avatar->mimeType,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    public function resume(Request $request, string $slug, int $id, ActorResolver $actors, GetCandidateResume $get): Response
    {
        $file = $get->execute($actors->fromRequest($request), $slug, $id);
        $name = str_ends_with(strtolower($file->name), '.pdf') ? $file->name : $file->name.'.pdf';
        $fallback = str_replace(['%', '/', '\\', '"'], '_', (string) preg_replace('/[^\x20-\x7E]/', '_', $name));

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name, $fallback),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    public function views(ListProfileViewsRequest $request, ActorResolver $actors, ListMyProfileViews $list): AnonymousResourceCollection
    {
        return PaginatedResources::of($list->execute($actors->fromRequest($request), $request->pageRequest()), ProfileViewResource::class, $request);
    }
}
