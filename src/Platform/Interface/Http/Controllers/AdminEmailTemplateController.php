<?php

namespace Src\Platform\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Platform\Application\Email\GetEmailTemplate;
use Src\Platform\Application\Email\ListEmailTemplates;
use Src\Platform\Application\Email\PreviewEmailTemplate;
use Src\Platform\Application\Email\PreviewEmailTemplatePdf;
use Src\Platform\Application\Email\ResetEmailTemplateOverride;
use Src\Platform\Application\Email\UpdateEmailTemplateOverride;
use Src\Platform\Interface\Http\Requests\EmailTemplateRequest;
use Src\Platform\Interface\Http\Resources\EmailTemplateResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Email templates for admins holding `email_templates.manage`. The catalog is fixed by the code, so the list is not paginated. */
final class AdminEmailTemplateController extends ApiController
{
    public function index(ListEmailTemplates $list): AnonymousResourceCollection
    {
        return EmailTemplateResource::collection($list->execute());
    }

    public function show(string $key, GetEmailTemplate $get): EmailTemplateResource
    {
        return new EmailTemplateResource($get->execute($key));
    }

    public function update(string $key, string $locale, EmailTemplateRequest $request, ActorResolver $actors, UpdateEmailTemplateOverride $update): EmailTemplateResource
    {
        return new EmailTemplateResource($update->execute(
            $actors->fromRequest($request), $key, $locale, (string) $request->subject(), (string) $request->body(), $request->ip(), $request->userAgent(),
        ));
    }

    /** Removes the override of one language: the default applies again. */
    public function destroy(string $key, string $locale, Request $request, ActorResolver $actors, ResetEmailTemplateOverride $reset): EmailTemplateResource
    {
        return new EmailTemplateResource($reset->execute($actors->fromRequest($request), $key, $locale, $request->ip(), $request->userAgent()));
    }

    /** Renders the template with sample values, using the draft subject and body in the request when given. Nothing is saved or sent. */
    public function preview(string $key, EmailTemplateRequest $request, PreviewEmailTemplate $preview): JsonResponse
    {
        $rendered = $preview->execute($key, $request->locale(), $request->subject(), $request->body());

        return new JsonResponse(['data' => ['subject' => $rendered->subject, 'html' => $rendered->html, 'text' => $rendered->text]]);
    }

    /** The same preview as a PDF (the subject, then the email as laid out), to open in a viewer. Nothing is saved or sent. */
    public function previewPdf(string $key, EmailTemplateRequest $request, PreviewEmailTemplatePdf $preview): Response
    {
        $pdf = $preview->execute($key, $request->locale(), $request->subject(), $request->body());

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="email-preview.pdf"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
