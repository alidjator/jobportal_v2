<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Assessment\Application\Invitations\AcceptInvitation;
use Src\Assessment\Application\Invitations\PreviewInvitation;
use Src\Assessment\Interface\Http\Requests\AcceptPsychologistInvitationRequest;
use Src\Assessment\Interface\Http\Resources\AcceptedInvitationResource;
use Src\Assessment\Interface\Http\Resources\InvitationPreviewResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ApiController;

/** The public side of a psychologist's invitation: the invitee has no account yet, so the token in the URL is the credential. */
final class PsychologistInvitationController extends ApiController
{
    public function show(Request $request, string $token, PreviewInvitation $preview): InvitationPreviewResource
    {
        return new InvitationPreviewResource($preview->execute($token, $request->ip()));
    }

    public function accept(AcceptPsychologistInvitationRequest $request, string $token, RequestContext $context, AcceptInvitation $accept): JsonResponse
    {
        return (new AcceptedInvitationResource($accept->execute($request->toCommand($token, $context))))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
