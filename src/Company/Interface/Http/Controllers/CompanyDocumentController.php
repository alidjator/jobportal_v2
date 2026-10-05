<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Company\Application\Documents\GetCompanyDocumentFile;
use Src\Company\Application\Documents\ListCompanyDocuments;
use Src\Company\Application\Documents\RemoveCompanyDocument;
use Src\Company\Application\Documents\SubmitCompanyDocument;
use Src\Company\Interface\Http\Requests\UploadCompanyDocumentRequest;
use Src\Company\Interface\Http\Resources\CompanyDocumentsResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The verification documents of the caller's own company, for company admins. A file has no public URL: it is sent by `file` only, as a
 * download that browsers may not render or reinterpret.
 */
final class CompanyDocumentController extends ApiController
{
    public function index(Request $request, RequestContext $context, ActorResolver $actors, ListCompanyDocuments $list): CompanyDocumentsResource
    {
        return new CompanyDocumentsResource($list->execute($actors->fromRequest($request), $context->language));
    }

    /** 201 when the document was created, 204 when it replaced an earlier one. */
    public function store(UploadCompanyDocumentRequest $request, int $typeId, ActorResolver $actors, SubmitCompanyDocument $submit): Response
    {
        $created = $submit->execute(
            $actors->fromRequest($request), $typeId, $request->contents(), $request->originalName(), $request->ip(), $request->userAgent(),
        );

        return new Response(status: $created ? Response::HTTP_CREATED : Response::HTTP_NO_CONTENT);
    }

    public function destroy(Request $request, int $typeId, ActorResolver $actors, RemoveCompanyDocument $remove): Response
    {
        $remove->execute($actors->fromRequest($request), $typeId, $request->ip(), $request->userAgent());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function file(Request $request, int $typeId, ActorResolver $actors, GetCompanyDocumentFile $get): Response
    {
        $file = $get->execute($actors->fromRequest($request), $typeId);

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->downloadName),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
