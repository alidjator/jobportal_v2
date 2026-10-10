<?php

namespace Src\JobInvitation\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\JobInvitation\Application\CreateInvitation;
use Src\JobInvitation\Application\InvitationViews;
use Src\JobInvitation\Application\ListJobInvitations;
use Src\JobInvitation\Interface\Http\Requests\CreateInvitationRequest;
use Src\JobInvitation\Interface\Http\Requests\ListInvitationsRequest;
use Src\JobInvitation\Interface\Http\Resources\CompanyInvitationResource;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;
use Symfony\Component\HttpFoundation\Response;

/** The invitations a company sends for one of its jobs. */
final class CompanyInvitationController extends ApiController
{
    public function index(ListInvitationsRequest $request, int $id, ActorResolver $actors, ListJobInvitations $list): AnonymousResourceCollection
    {
        return CompanyInvitationResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $id, $request->status(), $request->pageRequest()), $request));
    }

    public function store(CreateInvitationRequest $request, int $id, ActorResolver $actors, CreateInvitation $create, InvitationViews $views): JsonResponse
    {
        $invitation = $create->execute($actors->fromRequest($request), $id, $request->candidate(), $request->note());
        $view = $views->forCompany(new Page([$invitation], 1, new PageRequest))->items[0];

        return (new CompanyInvitationResource($view))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
