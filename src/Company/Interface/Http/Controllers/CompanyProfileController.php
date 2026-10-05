<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Company\Application\Profile\ChangeCompanySlug;
use Src\Company\Application\Profile\GetCompanyProfile;
use Src\Company\Application\Profile\UpdateCompanyProfile;
use Src\Company\Interface\Http\Requests\ChangeCompanySlugRequest;
use Src\Company\Interface\Http\Requests\UpdateCompanyProfileRequest;
use Src\Company\Interface\Http\Resources\CompanyProfileResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The signed-in member's own company profile: any member reads it, only a company admin changes it. */
final class CompanyProfileController extends ApiController
{
    public function show(Request $request, ActorResolver $actors, GetCompanyProfile $get): CompanyProfileResource
    {
        return new CompanyProfileResource($get->execute($actors->fromRequest($request)));
    }

    public function update(UpdateCompanyProfileRequest $request, ActorResolver $actors, UpdateCompanyProfile $update): Response
    {
        $update->execute($actors->fromRequest($request), $request->changes());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function changeSlug(ChangeCompanySlugRequest $request, ActorResolver $actors, ChangeCompanySlug $change): Response
    {
        $change->execute($actors->fromRequest($request), $request->slug());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
