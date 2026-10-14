<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Company\Application\Team\InviteMember;
use Src\Company\Application\Team\ResendInvitation;
use Src\Company\Application\Team\RevokeInvitation;
use Src\Company\Application\Team\UpdateInvitation;
use Src\Company\Interface\Http\Requests\InviteMemberRequest;
use Src\Company\Interface\Http\Requests\UpdateInvitationRequest;
use Src\Company\Interface\Http\Resources\IssuedInvitationResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The invitations of the signed-in company admin's own company. They are listed as rows of the team list (`GET company/members`). */
final class TeamInvitationController extends ApiController
{
    public function store(InviteMemberRequest $request, RequestContext $context, ActorResolver $actors, InviteMember $invite): JsonResponse
    {
        $invitation = $invite->execute(
            $actors->fromRequest($request), $request->name(), $request->email(), $request->role(), $request->permissions(), $context->language, $request->ip(), $request->userAgent(),
        );

        return (new IssuedInvitationResource($invitation))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(UpdateInvitationRequest $request, int $id, ActorResolver $actors, UpdateInvitation $update): Response
    {
        $update->execute($actors->fromRequest($request), $id, $request->name(), $request->role(), $request->permissions(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function resend(Request $request, int $id, RequestContext $context, ActorResolver $actors, ResendInvitation $resend): Response
    {
        $resend->execute($actors->fromRequest($request), $id, $context->language, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, RevokeInvitation $revoke): Response
    {
        $revoke->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
