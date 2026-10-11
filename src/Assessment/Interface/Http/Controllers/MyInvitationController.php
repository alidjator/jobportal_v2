<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Assessment\Application\Delivery\MyInvitations;
use Src\Assessment\Interface\Http\Requests\ListMyInvitationsRequest;
use Src\Assessment\Interface\Http\Resources\MyInvitationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The tests a job seeker was asked to take. */
final class MyInvitationController extends ApiController
{
    public function index(ListMyInvitationsRequest $request, ActorResolver $actors, MyInvitations $invitations): AnonymousResourceCollection
    {
        $page = $invitations->list($actors->fromRequest($request), $request->status(), $request->pageRequest());

        return MyInvitationResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, MyInvitations $invitations): MyInvitationResource
    {
        return new MyInvitationResource($invitations->get($actors->fromRequest($request), $id));
    }
}
