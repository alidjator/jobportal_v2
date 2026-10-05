<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Company\Application\Documents\ApproveCompanyDocument;
use Src\Company\Application\Documents\GetCompanyDocumentFileForAdmin;
use Src\Company\Application\Documents\ListCompanyDocumentsForAdmin;
use Src\Company\Application\Documents\RejectCompanyDocument;
use Src\Company\Interface\Http\Requests\ApproveCompanyDocumentRequest;
use Src\Company\Interface\Http\Requests\RejectCompanyDocumentRequest;
use Src\Company\Interface\Http\Resources\AdminCompanyDocumentsResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The verification documents of any company, for platform admins holding `companies.manage`. A file is sent as a download only, and every opening is audited. */
final class AdminCompanyDocumentController extends ApiController
{
    public function index(Request $request, int $id, ActorResolver $actors, ListCompanyDocumentsForAdmin $list): AdminCompanyDocumentsResource
    {
        return new AdminCompanyDocumentsResource($list->execute($actors->fromRequest($request), $id));
    }

    public function approve(ApproveCompanyDocumentRequest $request, int $id, int $typeId, ActorResolver $actors, ApproveCompanyDocument $approve): Response
    {
        $approve->execute($actors->fromRequest($request), $id, $typeId, $request->seenSubmittedAt(), $request->ip(), $request->userAgent());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function reject(RejectCompanyDocumentRequest $request, int $id, int $typeId, ActorResolver $actors, RejectCompanyDocument $reject): Response
    {
        $reject->execute(
            $actors->fromRequest($request), $id, $typeId, $request->seenSubmittedAt(), $request->reason(), $request->note(),
            $request->ip(), $request->userAgent(),
        );

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function file(Request $request, int $id, int $typeId, ActorResolver $actors, GetCompanyDocumentFileForAdmin $get): Response
    {
        $file = $get->execute($actors->fromRequest($request), $id, $typeId, $request->ip(), $request->userAgent());

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->downloadName),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
