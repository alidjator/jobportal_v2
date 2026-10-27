<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Team\ChangeTeamMember;
use Src\Assessment\Application\Team\InviteTeamMember;
use Src\Assessment\Application\Team\ListTeam;
use Src\Assessment\Application\Team\ListTeamInvitations;
use Src\Assessment\Application\Team\RevokeTeamInvitation;
use Src\Assessment\Interface\Http\Requests\ChangeTeamRoleRequest;
use Src\Assessment\Interface\Http\Requests\InviteTeamMemberRequest;
use Src\Assessment\Interface\Http\Resources\PsychologistInvitationResource;
use Src\Assessment\Interface\Http\Resources\PsychologistResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The leads of an institution manage its team: who is in it, who is invited, who is suspended and who leads. */
final class TeamController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListTeam $list): AnonymousResourceCollection
    {
        return PsychologistResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function invitations(Request $request, ActorResolver $actors, ListTeamInvitations $list): AnonymousResourceCollection
    {
        return PsychologistInvitationResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function invite(InviteTeamMemberRequest $request, RequestContext $context, ActorResolver $actors, InviteTeamMember $invite): JsonResponse
    {
        $invitation = $invite->execute(
            $actors->fromRequest($request), $request->string('email')->toString(), $request->string('name')->toString(), $request->role(),
            $context->language, $request->ip(), $request->userAgent(),
        );

        return (new PsychologistInvitationResource($invitation))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function revoke(Request $request, int $invitationId, ActorResolver $actors, RevokeTeamInvitation $revoke): Response
    {
        $revoke->execute($actors->fromRequest($request), $invitationId);

        return response()->noContent();
    }

    public function suspend(Request $request, int $psychologistId, ActorResolver $actors, ChangeTeamMember $change): Response
    {
        $change->suspend($actors->fromRequest($request), $psychologistId, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function restore(Request $request, int $psychologistId, ActorResolver $actors, ChangeTeamMember $change): Response
    {
        $change->restore($actors->fromRequest($request), $psychologistId, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function role(ChangeTeamRoleRequest $request, int $psychologistId, ActorResolver $actors, ChangeTeamMember $change): PsychologistResource
    {
        return new PsychologistResource($change->changeRole($actors->fromRequest($request), $psychologistId, $request->role(), $request->ip(), $request->userAgent()));
    }
}
