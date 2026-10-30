<?php

namespace Src\JobInvitation\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\JobInvitation\Application\CancelEmailInvitation;
use Src\JobInvitation\Application\EmailInvitationViews;
use Src\JobInvitation\Application\GetEmailInvitationCv;
use Src\JobInvitation\Application\ListEmailInvitations;
use Src\JobInvitation\Application\ResendEmailInvitation;
use Src\JobInvitation\Application\SendEmailInvitation;
use Src\JobInvitation\Interface\Http\Requests\ListEmailInvitationsRequest;
use Src\JobInvitation\Interface\Http\Requests\SendEmailInvitationRequest;
use Src\JobInvitation\Interface\Http\Resources\CompanyEmailInvitationResource;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The invitations by email a company sends for its jobs: people who sent a CV outside the platform, walked in, were referred or met the team at a job fair. */
final class CompanyEmailInvitationController extends ApiController
{
    public function index(ListEmailInvitationsRequest $request, int $id, ActorResolver $actors, ListEmailInvitations $list): AnonymousResourceCollection
    {
        return CompanyEmailInvitationResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $id, $request->status(), $request->pageRequest()), $request));
    }

    public function store(SendEmailInvitationRequest $request, int $id, ActorResolver $actors, RequestContext $context, SendEmailInvitation $send, EmailInvitationViews $views): JsonResponse
    {
        $invitation = $send->execute(
            $actors->fromRequest($request), $id, $request->personName(), $request->email(), $request->channel(), $request->note(), $request->cvContents(), $request->cvName(),
            $context->language, $request->ip(), $request->userAgent(),
        );
        $view = $views->forCompany(new Page([$invitation], 1, new PageRequest))->items[0];

        return (new CompanyEmailInvitationResource($view))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /** The CV attached to an open invitation; every read is audited. */
    public function cv(Request $request, int $id, ActorResolver $actors, GetEmailInvitationCv $get): Response
    {
        $file = $get->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());
        $name = str_ends_with(strtolower($file->name), '.pdf') ? $file->name : $file->name.'.pdf';
        $fallback = str_replace(['%', '/', '\\', '"'], '_', (string) preg_replace('/[^\x20-\x7E]/', '_', $name));

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name, $fallback),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    public function resend(Request $request, int $id, ActorResolver $actors, RequestContext $context, ResendEmailInvitation $resend, EmailInvitationViews $views): CompanyEmailInvitationResource
    {
        $invitation = $resend->execute($actors->fromRequest($request), $id, $context->language);

        return new CompanyEmailInvitationResource($views->forCompany(new Page([$invitation], 1, new PageRequest))->items[0]);
    }

    public function cancel(Request $request, int $id, ActorResolver $actors, CancelEmailInvitation $cancel): Response
    {
        $cancel->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
