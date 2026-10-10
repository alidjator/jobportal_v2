<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\CreateStage;
use Src\Recruitment\Application\DeleteStage;
use Src\Recruitment\Application\ListStages;
use Src\Recruitment\Application\RenameStage;
use Src\Recruitment\Application\ReorderStages;
use Src\Recruitment\Interface\Http\Requests\CreateStageRequest;
use Src\Recruitment\Interface\Http\Requests\RenameStageRequest;
use Src\Recruitment\Interface\Http\Requests\ReorderStagesRequest;
use Src\Recruitment\Interface\Http\Resources\StageResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The application stages of the caller's own company. Company admins and recruiters read them; only company admins change them. */
final class CompanyStageController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListStages $list): AnonymousResourceCollection
    {
        return StageResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function store(CreateStageRequest $request, ActorResolver $actors, CreateStage $create): JsonResponse
    {
        $stage = $create->execute($actors->fromRequest($request), $request->name(), $request->type());

        return (new StageResource($stage))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(RenameStageRequest $request, int $id, ActorResolver $actors, RenameStage $rename): StageResource
    {
        return new StageResource($rename->execute($actors->fromRequest($request), $id, $request->name()));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteStage $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    public function reorder(ReorderStagesRequest $request, ActorResolver $actors, ReorderStages $reorder): AnonymousResourceCollection
    {
        return StageResource::collection($reorder->execute($actors->fromRequest($request), $request->stageIds()));
    }
}
