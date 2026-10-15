<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Company\Application\Settings\GetCompanySettings;
use Src\Company\Application\Settings\UpdateCompanySettings;
use Src\Company\Interface\Http\Requests\UpdateCompanySettingsRequest;
use Src\Company\Interface\Http\Resources\CompanySettingResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The settings page of the signed-in member's own company: any member reads, a company admin changes. */
final class CompanySettingsController extends ApiController
{
    public function show(Request $request, ActorResolver $actors, GetCompanySettings $get): AnonymousResourceCollection
    {
        return CompanySettingResource::collection($get->execute($actors->fromRequest($request)));
    }

    public function update(UpdateCompanySettingsRequest $request, ActorResolver $actors, UpdateCompanySettings $update): AnonymousResourceCollection
    {
        return CompanySettingResource::collection($update->execute($actors->fromRequest($request), $request->values(), $request->ip(), $request->userAgent()));
    }
}
