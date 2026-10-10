<?php

namespace Src\JobInvitation\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\JobInvitation\Application\DeclineInvitation;
use Src\JobInvitation\Application\GetMyInvitation;
use Src\JobInvitation\Application\ListMyInvitations;
use Src\JobInvitation\Interface\Http\Requests\DeclineInvitationRequest;
use Src\JobInvitation\Interface\Http\Requests\ListInvitationsRequest;
use Src\JobInvitation\Interface\Http\Resources\MyInvitationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The invitations of the signed-in job seeker. An invitation that is missing or someone else's is a 404. */
final class MyInvitationController extends ApiController
{
    public function index(ListInvitationsRequest $request, ActorResolver $actors, ListMyInvitations $list): AnonymousResourceCollection
    {
        return MyInvitationResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $request->status(), $request->pageRequest()), $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetMyInvitation $get): MyInvitationResource
    {
        return new MyInvitationResource($get->execute($actors->fromRequest($request), $id));
    }

    public function decline(DeclineInvitationRequest $request, int $id, ActorResolver $actors, DeclineInvitation $decline): MyInvitationResource
    {
        return new MyInvitationResource($decline->execute($actors->fromRequest($request), $id, $request->reason()));
    }
}
