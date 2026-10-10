<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\AddApplicationNote;
use Src\Recruitment\Application\DeleteApplicationNote;
use Src\Recruitment\Application\ListApplicationNotes;
use Src\Recruitment\Application\UpdateApplicationNote;
use Src\Recruitment\Interface\Http\Requests\SaveApplicationNoteRequest;
use Src\Recruitment\Interface\Http\Resources\ApplicationNoteResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The internal notes of an application, for the members who may see its job. */
final class ApplicationNoteController extends ApiController
{
    public function index(Request $request, int $id, ActorResolver $actors, ListApplicationNotes $list): AnonymousResourceCollection
    {
        return ApplicationNoteResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    public function store(SaveApplicationNoteRequest $request, int $id, ActorResolver $actors, AddApplicationNote $add): JsonResponse
    {
        return (new ApplicationNoteResource($add->execute($actors->fromRequest($request), $id, $request->body())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveApplicationNoteRequest $request, int $id, int $noteId, ActorResolver $actors, UpdateApplicationNote $update): ApplicationNoteResource
    {
        return new ApplicationNoteResource($update->execute($actors->fromRequest($request), $id, $noteId, $request->body()));
    }

    public function destroy(Request $request, int $id, int $noteId, ActorResolver $actors, DeleteApplicationNote $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id, $noteId);

        return response()->noContent();
    }
}
