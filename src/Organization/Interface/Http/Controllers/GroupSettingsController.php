<?php

namespace Src\Organization\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Organization\Application\Settings\GetGroupSettings;
use Src\Organization\Application\Settings\UpdateGroupSettings;
use Src\Organization\Interface\Http\Requests\UpdateGroupSettingsRequest;
use Src\Organization\Interface\Http\Resources\GroupSettingResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The settings page of the signed-in member's group: any member reads, `group.settings.manage` changes. */
final class GroupSettingsController extends ApiController
{
    public function show(Request $request, ActorResolver $actors, GetGroupSettings $get): AnonymousResourceCollection
    {
        return GroupSettingResource::collection($get->execute($actors->fromRequest($request)));
    }

    public function update(UpdateGroupSettingsRequest $request, ActorResolver $actors, UpdateGroupSettings $update): AnonymousResourceCollection
    {
        return GroupSettingResource::collection($update->execute($actors->fromRequest($request), $request->values(), $request->ip(), $request->userAgent()));
    }
}
