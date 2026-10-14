<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Delivery\CancelInvitation;
use Src\Assessment\Application\Delivery\ChangeInvitationAttachment;
use Src\Assessment\Application\Delivery\GetInvitation;
use Src\Assessment\Application\Delivery\GetInvitationAttachment;
use Src\Assessment\Application\Delivery\ListApplicationInvitations;
use Src\Assessment\Application\Delivery\SendInvitation;
use Src\Assessment\Interface\Http\AttachmentResponse;
use Src\Assessment\Interface\Http\Requests\SendInvitationRequest;
use Src\Assessment\Interface\Http\Requests\UploadInvitationFileRequest;
use Src\Assessment\Interface\Http\Resources\InvitationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The tests sent for an application, for the company members who may see its job. */
final class InvitationController extends ApiController
{
    public function index(Request $request, int $id, ActorResolver $actors, ListApplicationInvitations $list): AnonymousResourceCollection
    {
        return InvitationResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    /** Sends a test: 201 for a new invitation, 200 when it replaced the active one. */
    public function store(SendInvitationRequest $request, int $id, ActorResolver $actors, SendInvitation $send): JsonResponse
    {
        $result = $send->execute($actors->fromRequest($request), $id, $request->toInput());

        return (new InvitationResource($result['view']))->response()->setStatusCode($result['replaced'] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_CREATED);
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetInvitation $get): InvitationResource
    {
        return new InvitationResource($get->execute($actors->fromRequest($request), $id));
    }

    public function cancel(Request $request, int $id, ActorResolver $actors, CancelInvitation $cancel): InvitationResource
    {
        return new InvitationResource($cancel->execute($actors->fromRequest($request), $id));
    }

    /** Puts the one file on the invitation, or replaces it. */
    public function attach(UploadInvitationFileRequest $request, int $id, ActorResolver $actors, ChangeInvitationAttachment $change): InvitationResource
    {
        return new InvitationResource($change->put($actors->fromRequest($request), $id, $request->contents(), $request->originalName()));
    }

    public function detach(Request $request, int $id, ActorResolver $actors, ChangeInvitationAttachment $change): InvitationResource
    {
        return new InvitationResource($change->remove($actors->fromRequest($request), $id));
    }

    /** The file as a download that browsers may not render or reinterpret; it has no public URL. */
    public function attachment(Request $request, int $id, ActorResolver $actors, GetInvitationAttachment $get): Response
    {
        return AttachmentResponse::of($get->forTeam($actors->fromRequest($request), $id));
    }
}
