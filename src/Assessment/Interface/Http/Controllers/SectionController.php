<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Authoring\AddSection;
use Src\Assessment\Application\Authoring\DeleteSection;
use Src\Assessment\Application\Authoring\ReorderSections;
use Src\Assessment\Application\Authoring\UpdateSection;
use Src\Assessment\Interface\Http\Requests\OrderRequest;
use Src\Assessment\Interface\Http\Requests\SectionRequest;
use Src\Assessment\Interface\Http\Resources\SectionResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The sections of a test of the author (see `TestController`). */
final class SectionController extends ApiController
{
    public function store(SectionRequest $request, int $id, ActorResolver $actors, AddSection $add): JsonResponse
    {
        $section = $add->execute($actors->fromRequest($request), $id, $request->draft());

        return (new SectionResource($section))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(SectionRequest $request, int $sectionId, ActorResolver $actors, UpdateSection $update): SectionResource
    {
        return new SectionResource($update->execute($actors->fromRequest($request), $sectionId, $request->draft()));
    }

    public function destroy(Request $request, int $sectionId, ActorResolver $actors, DeleteSection $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $sectionId);

        return response()->noContent();
    }

    public function reorder(OrderRequest $request, int $id, ActorResolver $actors, ReorderSections $reorder): AnonymousResourceCollection
    {
        return SectionResource::collection($reorder->execute($actors->fromRequest($request), $id, $request->ids()));
    }
}
