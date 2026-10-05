<?php

namespace Src\Platform\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Platform\Application\Settings\ListCompanySettings;
use Src\Platform\Application\Settings\ResetCompanySetting;
use Src\Platform\Application\Settings\SetCompanySetting;
use Src\Platform\Interface\Http\Requests\UpdateSettingRequest;
use Src\Platform\Interface\Http\Resources\AdminCompanySettingResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The per-company overrides of the settings, for admins holding `settings.manage`. The list holds only the overridable settings (bounded by the code), so it is not paginated. */
final class AdminCompanySettingController extends ApiController
{
    public function index(int $id, ListCompanySettings $list): AnonymousResourceCollection
    {
        return AdminCompanySettingResource::collection($list->execute($id));
    }

    public function update(int $id, string $key, UpdateSettingRequest $request, ActorResolver $actors, SetCompanySetting $set): AdminCompanySettingResource
    {
        return new AdminCompanySettingResource($set->execute($actors->fromRequest($request), $id, $key, $request->value(), $request->ip(), $request->userAgent()));
    }

    /** Takes the override away, so the global value applies to the company again. */
    public function destroy(int $id, string $key, Request $request, ActorResolver $actors, ResetCompanySetting $reset): AdminCompanySettingResource
    {
        return new AdminCompanySettingResource($reset->execute($actors->fromRequest($request), $id, $key, $request->ip(), $request->userAgent()));
    }
}
