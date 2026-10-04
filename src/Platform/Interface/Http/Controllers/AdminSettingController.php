<?php

namespace Src\Platform\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Platform\Application\Settings\ListSettings;
use Src\Platform\Application\Settings\ResetSetting;
use Src\Platform\Application\Settings\UpdateSetting;
use Src\Platform\Interface\Http\Requests\UpdateSettingRequest;
use Src\Platform\Interface\Http\Resources\AdminSettingResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Global settings for admins holding `settings.manage`. The list is the whole catalog (bounded by the code), so it is not paginated. */
final class AdminSettingController extends ApiController
{
    public function index(ListSettings $list): AnonymousResourceCollection
    {
        return AdminSettingResource::collection($list->execute());
    }

    public function update(string $key, UpdateSettingRequest $request, ActorResolver $actors, UpdateSetting $update): AdminSettingResource
    {
        return new AdminSettingResource($update->execute($actors->fromRequest($request), $key, $request->value(), $request->ip(), $request->userAgent()));
    }

    /** Resets the setting to its default. */
    public function destroy(string $key, Request $request, ActorResolver $actors, ResetSetting $reset): AdminSettingResource
    {
        return new AdminSettingResource($reset->execute($actors->fromRequest($request), $key, $request->ip(), $request->userAgent()));
    }
}
