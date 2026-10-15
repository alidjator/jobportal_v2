<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Company\Application\NameChange\ApproveNameChange;
use Src\Company\Application\NameChange\GetNameChangeDocumentForAdmin;
use Src\Company\Application\NameChange\GetNameChangeRequestForAdmin;
use Src\Company\Application\NameChange\ListNameChangeRequestsForAdmin;
use Src\Company\Application\NameChange\RejectNameChange;
use Src\Company\Interface\Http\Requests\ListNameChangeRequestsRequest;
use Src\Company\Interface\Http\Requests\RejectNameChangeRequest;
use Src\Company\Interface\Http\Resources\AdminNameChangeResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The name change requests of every company, for platform admins holding `companies.manage`. The proof is sent as a download only, and every opening is audited. */
final class AdminNameChangeController extends ApiController
{
    public function index(ListNameChangeRequestsRequest $request, ActorResolver $actors, ListNameChangeRequestsForAdmin $list): AnonymousResourceCollection
    {
        return AdminNameChangeResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $request->status(), $request->pageRequest()), $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetNameChangeRequestForAdmin $get): AdminNameChangeResource
    {
        return new AdminNameChangeResource($get->execute($actors->fromRequest($request), $id));
    }

    public function document(Request $request, int $id, ActorResolver $actors, GetNameChangeDocumentForAdmin $get): Response
    {
        $file = $get->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return new Response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $file->downloadName),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    /** The request as it is afterwards. */
    public function approve(Request $request, int $id, ActorResolver $actors, ApproveNameChange $approve, GetNameChangeRequestForAdmin $get): AdminNameChangeResource
    {
        $actor = $actors->fromRequest($request);
        $approve->execute($actor, $id, $request->ip(), $request->userAgent());

        return new AdminNameChangeResource($get->execute($actor, $id));
    }

    public function reject(RejectNameChangeRequest $request, int $id, ActorResolver $actors, RejectNameChange $reject, GetNameChangeRequestForAdmin $get): AdminNameChangeResource
    {
        $actor = $actors->fromRequest($request);
        $reject->execute($actor, $id, $request->note(), $request->ip(), $request->userAgent());

        return new AdminNameChangeResource($get->execute($actor, $id));
    }
}
