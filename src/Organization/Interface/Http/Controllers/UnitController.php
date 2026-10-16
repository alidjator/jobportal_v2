<?php

namespace Src\Organization\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Organization\Application\Structure\ChangeUnit;
use Src\Organization\Application\Structure\ChangeUnitStatus;
use Src\Organization\Application\Structure\CreateUnit;
use Src\Organization\Application\Structure\GetUnit;
use Src\Organization\Application\Structure\ListUnits;
use Src\Organization\Interface\Http\Requests\StructureReadRequest;
use Src\Organization\Interface\Http\Requests\UnitRequest;
use Src\Organization\Interface\Http\Resources\UnitResource;
use Src\Organization\Interface\Http\Resources\UnitVersionResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The organisation units of a legal entity; `?as_of=<day>` reads the structure as it was or will be on that day. */
final class UnitController extends ApiController
{
    public function index(StructureReadRequest $request, ActorResolver $actors, ListUnits $list): AnonymousResourceCollection
    {
        return UnitResource::collection($list->execute($actors->fromRequest($request), $request->legalEntityId(), $request->asOf()));
    }

    public function show(StructureReadRequest $request, int $id, ActorResolver $actors, GetUnit $get): UnitResource
    {
        return new UnitResource($get->execute($actors->fromRequest($request), $id, $request->asOf()));
    }

    public function versions(Request $request, int $id, ActorResolver $actors, GetUnit $get): AnonymousResourceCollection
    {
        return UnitVersionResource::collection($get->history($actors->fromRequest($request), $id));
    }

    public function store(UnitRequest $request, ActorResolver $actors, CreateUnit $create): JsonResponse
    {
        $unit = $create->execute(
            $actors->fromRequest($request), $request->legalEntityId(), $request->code(), $request->unit(), $request->validFrom(), $request->reason(), $request->ip(), $request->userAgent(),
        );

        return (new UnitResource($unit))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UnitRequest $request, int $id, ActorResolver $actors, ChangeUnit $change): UnitResource
    {
        return new UnitResource($change->execute($actors->fromRequest($request), $id, $request->changes(), $request->validFrom(), $request->reason(), $request->ip(), $request->userAgent()));
    }

    public function deactivate(Request $request, int $id, ActorResolver $actors, ChangeUnitStatus $status): UnitResource
    {
        return new UnitResource($status->execute($actors->fromRequest($request), $id, false, $request->ip(), $request->userAgent()));
    }

    public function reactivate(Request $request, int $id, ActorResolver $actors, ChangeUnitStatus $status): UnitResource
    {
        return new UnitResource($status->execute($actors->fromRequest($request), $id, true, $request->ip(), $request->userAgent()));
    }
}
