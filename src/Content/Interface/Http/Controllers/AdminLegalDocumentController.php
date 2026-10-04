<?php

namespace Src\Content\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Content\Application\LegalDocuments\CreateLegalDocumentDraft;
use Src\Content\Application\LegalDocuments\DeleteLegalDocumentDraft;
use Src\Content\Application\LegalDocuments\GetLegalDocument;
use Src\Content\Application\LegalDocuments\ListLegalDocuments;
use Src\Content\Application\LegalDocuments\PublishLegalDocument;
use Src\Content\Application\LegalDocuments\UpdateLegalDocumentDraft;
use Src\Content\Interface\Http\Requests\LegalDocumentRequest;
use Src\Content\Interface\Http\Requests\ListLegalDocumentsRequest;
use Src\Content\Interface\Http\Resources\AdminLegalDocumentResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Legal document management for admins holding `legal_documents.manage`. */
final class AdminLegalDocumentController extends ApiController
{
    public function index(ListLegalDocumentsRequest $request, ListLegalDocuments $list): AnonymousResourceCollection
    {
        return AdminLegalDocumentResource::collection($list->execute($request->type()));
    }

    public function show(int $id, GetLegalDocument $get): AdminLegalDocumentResource
    {
        return new AdminLegalDocumentResource($get->execute($id));
    }

    public function store(LegalDocumentRequest $request, ActorResolver $actors, CreateLegalDocumentDraft $create): JsonResponse
    {
        $document = $create->execute($actors->fromRequest($request), $request->type(), $request->title(), $request->body());

        return (new AdminLegalDocumentResource($document))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(int $id, LegalDocumentRequest $request, UpdateLegalDocumentDraft $update): AdminLegalDocumentResource
    {
        return new AdminLegalDocumentResource($update->execute($id, $request->title(), $request->body()));
    }

    public function destroy(int $id, DeleteLegalDocumentDraft $delete): Response
    {
        $delete->execute($id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function publish(int $id, Request $request, ActorResolver $actors, PublishLegalDocument $publish): AdminLegalDocumentResource
    {
        return new AdminLegalDocumentResource($publish->execute($actors->fromRequest($request), $id));
    }
}
