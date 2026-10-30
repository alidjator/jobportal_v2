<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\Checks\CreateCheckType;
use Src\Recruitment\Application\Checks\ListCheckTypes;
use Src\Recruitment\Application\Checks\UpdateCheckType;
use Src\Recruitment\Interface\Http\Requests\CreateCheckTypeRequest;
use Src\Recruitment\Interface\Http\Requests\UpdateCheckTypeRequest;
use Src\Recruitment\Interface\Http\Resources\CheckTypeResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The kinds of pre-employment check a company defines (`checks.manage`). */
final class CheckTypeController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListCheckTypes $list): AnonymousResourceCollection
    {
        return CheckTypeResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function store(CreateCheckTypeRequest $request, ActorResolver $actors, CreateCheckType $create): JsonResponse
    {
        $type = $create->execute($actors->fromRequest($request), $request->typeName(), $request->kind(), $request->instructions(), $request->requiresConsent());

        return (new CheckTypeResource($type))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCheckTypeRequest $request, int $id, ActorResolver $actors, UpdateCheckType $update): CheckTypeResource
    {
        return new CheckTypeResource($update->execute($actors->fromRequest($request), $id, $request->typeName(), $request->instructions(), $request->requiresConsent()));
    }

    public function deactivate(Request $request, int $id, ActorResolver $actors, UpdateCheckType $update): CheckTypeResource
    {
        return new CheckTypeResource($update->setActive($actors->fromRequest($request), $id, false));
    }

    public function reactivate(Request $request, int $id, ActorResolver $actors, UpdateCheckType $update): CheckTypeResource
    {
        return new CheckTypeResource($update->setActive($actors->fromRequest($request), $id, true));
    }
}
