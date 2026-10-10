<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Company\Application\Team\InviteMember;
use Src\Company\Application\Team\ListPendingInvitations;
use Src\Company\Application\Team\RevokeInvitation;
use Src\Company\Interface\Http\Requests\InviteMemberRequest;
use Src\Company\Interface\Http\Resources\IssuedInvitationResource;
use Src\Company\Interface\Http\Resources\PendingInvitationResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The invitations of the signed-in company admin's own company. */
final class TeamInvitationController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListPendingInvitations $list): AnonymousResourceCollection
    {
        return PendingInvitationResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function store(InviteMemberRequest $request, RequestContext $context, ActorResolver $actors, InviteMember $invite): JsonResponse
    {
        $invitation = $invite->execute($actors->fromRequest($request), $request->email(), $request->role(), $context->language, $request->ip(), $request->userAgent());

        return (new IssuedInvitationResource($invitation))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, RevokeInvitation $revoke): Response
    {
        $revoke->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
