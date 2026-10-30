<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Company\Application\Presets\CreatePermissionPreset;
use Src\Company\Application\Presets\DeletePermissionPreset;
use Src\Company\Application\Presets\ListPermissionPresets;
use Src\Company\Application\Presets\UpdatePermissionPreset;
use Src\Company\Interface\Http\Requests\PermissionPresetRequest;
use Src\Company\Interface\Http\Resources\PermissionPresetResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The permission presets of a company: sets of permissions a company admin saved under a name to give the same access to several members. */
final class PermissionPresetController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListPermissionPresets $list): AnonymousResourceCollection
    {
        return PermissionPresetResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function store(PermissionPresetRequest $request, ActorResolver $actors, CreatePermissionPreset $create): JsonResponse
    {
        $preset = $create->execute($actors->fromRequest($request), $request->presetName(), $request->permissions(), $request->ip(), $request->userAgent());

        return (new PermissionPresetResource($preset))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(PermissionPresetRequest $request, int $id, ActorResolver $actors, UpdatePermissionPreset $update): PermissionPresetResource
    {
        return new PermissionPresetResource($update->execute($actors->fromRequest($request), $id, $request->presetName(), $request->permissions(), $request->ip(), $request->userAgent()));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeletePermissionPreset $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
