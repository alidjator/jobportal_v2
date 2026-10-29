<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Assessment\Application\Institution\ChangeInstitutionLogo;
use Src\Assessment\Application\Institution\GetInstitution;
use Src\Assessment\Application\Institution\GetInstitutionLogo;
use Src\Assessment\Application\Institution\UpdateInstitution;
use Src\Assessment\Interface\Http\AttachmentResponse;
use Src\Assessment\Interface\Http\Requests\InstitutionRequest;
use Src\Assessment\Interface\Http\Requests\UploadImageRequest;
use Src\Assessment\Interface\Http\Resources\ProviderResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The psychologist's own institution: any of its psychologists reads it, its leads change the identity and the logo. */
final class InstitutionController extends ApiController
{
    public function show(Request $request, ActorResolver $actors, GetInstitution $get): ProviderResource
    {
        return new ProviderResource($get->execute($actors->fromRequest($request)));
    }

    public function update(InstitutionRequest $request, ActorResolver $actors, UpdateInstitution $update): ProviderResource
    {
        return new ProviderResource($update->execute($actors->fromRequest($request), $request->changes()));
    }

    public function logo(Request $request, ActorResolver $actors, GetInstitutionLogo $get): Response
    {
        return AttachmentResponse::of($get->execute($actors->fromRequest($request)));
    }

    public function storeLogo(UploadImageRequest $request, ActorResolver $actors, ChangeInstitutionLogo $change): Response
    {
        $change->put($actors->fromRequest($request), $request->contents());

        return response()->noContent();
    }

    public function destroyLogo(Request $request, ActorResolver $actors, ChangeInstitutionLogo $change): Response
    {
        $change->remove($actors->fromRequest($request));

        return response()->noContent();
    }
}
