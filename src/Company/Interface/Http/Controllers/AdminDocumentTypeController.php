<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Company\Application\Documents\CreateDocumentType;
use Src\Company\Application\Documents\ListDocumentTypes;
use Src\Company\Application\Documents\UpdateDocumentType;
use Src\Company\Interface\Http\Requests\DocumentTypeRequest;
use Src\Company\Interface\Http\Resources\DocumentTypeResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Document types for platform admins holding `companies.manage`. There is no delete: retire a type with `is_active`. */
final class AdminDocumentTypeController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListDocumentTypes $list): AnonymousResourceCollection
    {
        return DocumentTypeResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function store(DocumentTypeRequest $request, ActorResolver $actors, CreateDocumentType $create): JsonResponse
    {
        $type = $create->execute(
            $actors->fromRequest($request), $request->name(), $request->isRequired(), $request->isActive(), $request->sortOrder(),
            $request->ip(), $request->userAgent(),
        );

        return (new DocumentTypeResource($type))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(int $id, DocumentTypeRequest $request, ActorResolver $actors, UpdateDocumentType $update): DocumentTypeResource
    {
        return new DocumentTypeResource($update->execute(
            $actors->fromRequest($request), $id, $request->name(), $request->isRequired(), $request->isActive(), $request->sortOrder(),
            $request->ip(), $request->userAgent(),
        ));
    }
}
