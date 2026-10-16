<?php

namespace Src\Organization\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Organization\Application\Structure\ChangePosition;
use Src\Organization\Application\Structure\ChangePositionStatus;
use Src\Organization\Application\Structure\CreatePosition;
use Src\Organization\Application\Structure\GetPosition;
use Src\Organization\Application\Structure\ListPositions;
use Src\Organization\Interface\Http\Requests\PositionRequest;
use Src\Organization\Interface\Http\Requests\StructureReadRequest;
use Src\Organization\Interface\Http\Resources\PositionResource;
use Src\Organization\Interface\Http\Resources\PositionVersionResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The positions of a legal entity; `?as_of=<day>` reads the structure as it was or will be on that day. */
final class PositionController extends ApiController
{
    public function index(StructureReadRequest $request, ActorResolver $actors, ListPositions $list): AnonymousResourceCollection
    {
        return PositionResource::collection($list->execute($actors->fromRequest($request), $request->legalEntityId(), $request->asOf()));
    }

    public function show(StructureReadRequest $request, int $id, ActorResolver $actors, GetPosition $get): PositionResource
    {
        return new PositionResource($get->execute($actors->fromRequest($request), $id, $request->asOf()));
    }

    public function versions(Request $request, int $id, ActorResolver $actors, GetPosition $get): AnonymousResourceCollection
    {
        return PositionVersionResource::collection($get->history($actors->fromRequest($request), $id));
    }

    public function store(PositionRequest $request, ActorResolver $actors, CreatePosition $create): JsonResponse
    {
        $position = $create->execute(
            $actors->fromRequest($request), $request->legalEntityId(), $request->code(), $request->position(), $request->validFrom(), $request->reason(), $request->ip(), $request->userAgent(),
        );

        return (new PositionResource($position))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(PositionRequest $request, int $id, ActorResolver $actors, ChangePosition $change): PositionResource
    {
        return new PositionResource($change->execute($actors->fromRequest($request), $id, $request->changes(), $request->validFrom(), $request->reason(), $request->ip(), $request->userAgent()));
    }

    public function deactivate(Request $request, int $id, ActorResolver $actors, ChangePositionStatus $status): PositionResource
    {
        return new PositionResource($status->execute($actors->fromRequest($request), $id, false, $request->ip(), $request->userAgent()));
    }

    public function reactivate(Request $request, int $id, ActorResolver $actors, ChangePositionStatus $status): PositionResource
    {
        return new PositionResource($status->execute($actors->fromRequest($request), $id, true, $request->ip(), $request->userAgent()));
    }
}
