<?php

namespace Src\Organization\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Organization\Application\Structure\CostCentreCatalog;
use Src\Organization\Application\Structure\GradeCatalog;
use Src\Organization\Application\Structure\UnitTypeCatalog;
use Src\Organization\Interface\Http\Requests\CostCentreRequest;
use Src\Organization\Interface\Http\Requests\GradeRequest;
use Src\Organization\Interface\Http\Requests\StructureReadRequest;
use Src\Organization\Interface\Http\Requests\UnitTypeRequest;
use Src\Organization\Interface\Http\Resources\CostCentreResource;
use Src\Organization\Interface\Http\Resources\GradeResource;
use Src\Organization\Interface\Http\Resources\UnitTypeResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The grades and unit types of the caller's group and the cost centres of its legal entities; `group.structure.manage` changes them, any group member reads. */
final class StructureCatalogController extends ApiController
{
    public function grades(Request $request, ActorResolver $actors, GradeCatalog $catalog): AnonymousResourceCollection
    {
        return GradeResource::collection($catalog->list($actors->fromRequest($request)));
    }

    public function storeGrade(GradeRequest $request, ActorResolver $actors, GradeCatalog $catalog): JsonResponse
    {
        $grade = $catalog->create($actors->fromRequest($request), $request->code(), (string) $request->name(), (int) $request->rank(), $request->ip(), $request->userAgent());

        return (new GradeResource($grade))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateGrade(GradeRequest $request, int $id, ActorResolver $actors, GradeCatalog $catalog): GradeResource
    {
        return new GradeResource($catalog->update($actors->fromRequest($request), $id, $request->name(), $request->rank(), $request->ip(), $request->userAgent()));
    }

    public function deactivateGrade(Request $request, int $id, ActorResolver $actors, GradeCatalog $catalog): GradeResource
    {
        return new GradeResource($catalog->changeStatus($actors->fromRequest($request), $id, false, $request->ip(), $request->userAgent()));
    }

    public function reactivateGrade(Request $request, int $id, ActorResolver $actors, GradeCatalog $catalog): GradeResource
    {
        return new GradeResource($catalog->changeStatus($actors->fromRequest($request), $id, true, $request->ip(), $request->userAgent()));
    }

    public function unitTypes(Request $request, ActorResolver $actors, UnitTypeCatalog $catalog): AnonymousResourceCollection
    {
        return UnitTypeResource::collection($catalog->list($actors->fromRequest($request)));
    }

    public function storeUnitType(UnitTypeRequest $request, ActorResolver $actors, UnitTypeCatalog $catalog): JsonResponse
    {
        $type = $catalog->create($actors->fromRequest($request), $request->code(), (string) $request->name(), (int) $request->levelOrder(), $request->ip(), $request->userAgent());

        return (new UnitTypeResource($type))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateUnitType(UnitTypeRequest $request, int $id, ActorResolver $actors, UnitTypeCatalog $catalog): UnitTypeResource
    {
        return new UnitTypeResource($catalog->update($actors->fromRequest($request), $id, $request->name(), $request->levelOrder(), $request->ip(), $request->userAgent()));
    }

    public function deactivateUnitType(Request $request, int $id, ActorResolver $actors, UnitTypeCatalog $catalog): UnitTypeResource
    {
        return new UnitTypeResource($catalog->changeStatus($actors->fromRequest($request), $id, false, $request->ip(), $request->userAgent()));
    }

    public function reactivateUnitType(Request $request, int $id, ActorResolver $actors, UnitTypeCatalog $catalog): UnitTypeResource
    {
        return new UnitTypeResource($catalog->changeStatus($actors->fromRequest($request), $id, true, $request->ip(), $request->userAgent()));
    }

    public function costCentres(StructureReadRequest $request, ActorResolver $actors, CostCentreCatalog $catalog): AnonymousResourceCollection
    {
        return CostCentreResource::collection($catalog->list($actors->fromRequest($request), $request->legalEntityId()));
    }

    public function storeCostCentre(CostCentreRequest $request, ActorResolver $actors, CostCentreCatalog $catalog): JsonResponse
    {
        $centre = $catalog->create($actors->fromRequest($request), $request->legalEntityId(), $request->code(), $request->name(), $request->ip(), $request->userAgent());

        return (new CostCentreResource($centre))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateCostCentre(CostCentreRequest $request, int $id, ActorResolver $actors, CostCentreCatalog $catalog): CostCentreResource
    {
        return new CostCentreResource($catalog->update($actors->fromRequest($request), $id, $request->name(), $request->ip(), $request->userAgent()));
    }

    public function deactivateCostCentre(Request $request, int $id, ActorResolver $actors, CostCentreCatalog $catalog): CostCentreResource
    {
        return new CostCentreResource($catalog->changeStatus($actors->fromRequest($request), $id, false, $request->ip(), $request->userAgent()));
    }

    public function reactivateCostCentre(Request $request, int $id, ActorResolver $actors, CostCentreCatalog $catalog): CostCentreResource
    {
        return new CostCentreResource($catalog->changeStatus($actors->fromRequest($request), $id, true, $request->ip(), $request->userAgent()));
    }
}
