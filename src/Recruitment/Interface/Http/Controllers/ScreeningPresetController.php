<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\CreateScreeningPreset;
use Src\Recruitment\Application\DeleteScreeningPreset;
use Src\Recruitment\Application\GetScreeningPreset;
use Src\Recruitment\Application\ListScreeningPresets;
use Src\Recruitment\Application\UpdateScreeningPreset;
use Src\Recruitment\Interface\Http\Requests\ListScreeningPresetsRequest;
use Src\Recruitment\Interface\Http\Requests\SaveScreeningPresetRequest;
use Src\Recruitment\Interface\Http\Resources\ScreeningPresetResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The screening presets of the caller's own company. Every member who can pick questions reads them; only company admins change them. */
final class ScreeningPresetController extends ApiController
{
    public function index(ListScreeningPresetsRequest $request, ActorResolver $actors, ListScreeningPresets $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->search(), $request->jobLevelId(), $request->pageRequest());

        return ScreeningPresetResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetScreeningPreset $get): ScreeningPresetResource
    {
        return new ScreeningPresetResource($get->execute($actors->fromRequest($request), $id));
    }

    public function store(SaveScreeningPresetRequest $request, ActorResolver $actors, CreateScreeningPreset $create): JsonResponse
    {
        $preset = $create->execute($actors->fromRequest($request), $request->name(), $request->jobLevelId(), $request->items());

        return (new ScreeningPresetResource($preset))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(SaveScreeningPresetRequest $request, int $id, ActorResolver $actors, UpdateScreeningPreset $update): ScreeningPresetResource
    {
        return new ScreeningPresetResource($update->execute($actors->fromRequest($request), $id, $request->name(), $request->jobLevelId(), $request->items()));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteScreeningPreset $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
