<?php

namespace Src\Organization\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Organization\Application\Group\ChangeLegalEntityStatus;
use Src\Organization\Application\Group\CreateLegalEntity;
use Src\Organization\Application\Group\GetMyGroup;
use Src\Organization\Application\Group\UpdateLegalEntity;
use Src\Organization\Interface\Http\Requests\LegalEntityRequest;
use Src\Organization\Interface\Http\Requests\UpdateLegalEntityRequest;
use Src\Organization\Interface\Http\Resources\GroupResource;
use Src\Organization\Interface\Http\Resources\LegalEntityResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The group of the signed-in company member and its legal entities; the group permissions decide who may change them. */
final class OrganizationController extends ApiController
{
    public function group(Request $request, ActorResolver $actors, GetMyGroup $get): GroupResource
    {
        return new GroupResource($get->execute($actors->fromRequest($request)));
    }

    public function storeLegalEntity(LegalEntityRequest $request, ActorResolver $actors, CreateLegalEntity $create): JsonResponse
    {
        $entity = $create->execute($actors->fromRequest($request), $request->entity(), $request->ip(), $request->userAgent());

        return (new LegalEntityResource($entity))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateLegalEntity(UpdateLegalEntityRequest $request, int $id, ActorResolver $actors, UpdateLegalEntity $update): LegalEntityResource
    {
        return new LegalEntityResource($update->execute($actors->fromRequest($request), $id, $request->changes(), $request->ip(), $request->userAgent()));
    }

    public function deactivateLegalEntity(Request $request, int $id, ActorResolver $actors, ChangeLegalEntityStatus $status): LegalEntityResource
    {
        return new LegalEntityResource($status->execute($actors->fromRequest($request), $id, false, $request->ip(), $request->userAgent()));
    }

    public function reactivateLegalEntity(Request $request, int $id, ActorResolver $actors, ChangeLegalEntityStatus $status): LegalEntityResource
    {
        return new LegalEntityResource($status->execute($actors->fromRequest($request), $id, true, $request->ip(), $request->userAgent()));
    }
}
