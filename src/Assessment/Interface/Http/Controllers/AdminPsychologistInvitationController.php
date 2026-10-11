<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Invitations\InvitePsychologist;
use Src\Assessment\Application\Invitations\ListPendingInvitations;
use Src\Assessment\Application\Invitations\RevokeInvitation;
use Src\Assessment\Interface\Http\Requests\InvitePsychologistRequest;
use Src\Assessment\Interface\Http\Resources\PsychologistInvitationResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The invitations of a provider's psychologists, for platform admins holding `assessment_providers.manage`. */
final class AdminPsychologistInvitationController extends ApiController
{
    public function index(Request $request, int $id, ActorResolver $actors, ListPendingInvitations $list): AnonymousResourceCollection
    {
        return PsychologistInvitationResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    public function store(InvitePsychologistRequest $request, int $id, RequestContext $context, ActorResolver $actors, InvitePsychologist $invite): JsonResponse
    {
        $invitation = $invite->execute($actors->fromRequest($request), $request->toCommand($id, $context));

        return (new PsychologistInvitationResource($invitation))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function destroy(Request $request, int $id, int $invitationId, ActorResolver $actors, RevokeInvitation $revoke): Response
    {
        $revoke->execute($actors->fromRequest($request), $id, $invitationId);

        return response()->noContent();
    }
}
