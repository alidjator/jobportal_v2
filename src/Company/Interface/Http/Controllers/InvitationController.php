<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Company\Application\Invitations\AcceptInvitation;
use Src\Company\Application\Invitations\PreviewInvitation;
use Src\Company\Interface\Http\Requests\AcceptInvitationRequest;
use Src\Company\Interface\Http\Resources\AccountSessionResource;
use Src\Company\Interface\Http\Resources\InvitationPreviewResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ApiController;

/** The public side of an invitation: the invitee has no account yet, so the token in the URL is the credential. */
final class InvitationController extends ApiController
{
    public function show(Request $request, string $token, PreviewInvitation $preview): InvitationPreviewResource
    {
        return new InvitationPreviewResource($preview->execute($token, $request->ip()));
    }

    public function accept(AcceptInvitationRequest $request, string $token, RequestContext $context, AcceptInvitation $accept): JsonResponse
    {
        return (new AccountSessionResource($accept->execute($request->toCommand($token, $context))))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
