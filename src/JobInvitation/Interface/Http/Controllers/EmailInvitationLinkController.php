<?php

namespace Src\JobInvitation\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\JobInvitation\Application\ChooseEmailInvitationCv;
use Src\JobInvitation\Application\ClaimEmailInvitation;
use Src\JobInvitation\Application\DeclineEmailInvitationByToken;
use Src\JobInvitation\Application\ShowEmailInvitation;
use Src\JobInvitation\Interface\Http\Requests\ClaimEmailInvitationRequest;
use Src\JobInvitation\Interface\Http\Resources\ClaimedEmailInvitationResource;
use Src\JobInvitation\Interface\Http\Resources\PublicEmailInvitationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The person's side of an invitation by email: read the link and refuse it (public), claim it and choose what happens to the CV (a signed-in job seeker). */
final class EmailInvitationLinkController extends ApiController
{
    public function show(string $token, ShowEmailInvitation $show): PublicEmailInvitationResource
    {
        return new PublicEmailInvitationResource($show->execute($token));
    }

    public function decline(string $token, DeclineEmailInvitationByToken $decline): Response
    {
        $decline->execute($token);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function claim(ClaimEmailInvitationRequest $request, string $token, ActorResolver $actors, ClaimEmailInvitation $claim): ClaimedEmailInvitationResource
    {
        return new ClaimedEmailInvitationResource($claim->execute($actors->fromRequest($request), $token));
    }

    /** Copies the CV the company sent into the person's resumes and deletes the company's copy. */
    public function useCv(Request $request, string $token, ActorResolver $actors, ChooseEmailInvitationCv $choose): JsonResponse
    {
        return new JsonResponse(['data' => ['resume_id' => $choose->use($actors->fromRequest($request), $token)]], Response::HTTP_CREATED);
    }

    /** Refuses the CV the company sent: the company's copy is deleted. */
    public function discardCv(Request $request, string $token, ActorResolver $actors, ChooseEmailInvitationCv $choose): Response
    {
        $choose->discard($actors->fromRequest($request), $token);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
