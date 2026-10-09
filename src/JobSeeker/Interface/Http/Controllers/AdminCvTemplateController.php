<?php

namespace Src\JobSeeker\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\JobSeeker\Application\Cv\ListCvTemplatesForAdmin;
use Src\JobSeeker\Application\Cv\UpdateCvTemplate;
use Src\JobSeeker\Interface\Http\Requests\UpdateCvTemplateRequest;
use Src\JobSeeker\Interface\Http\Resources\AdminCvTemplateResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The CV templates for platform admins holding `cv_templates.manage`. */
final class AdminCvTemplateController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListCvTemplatesForAdmin $list): AnonymousResourceCollection
    {
        return AdminCvTemplateResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function update(UpdateCvTemplateRequest $request, string $key, ActorResolver $actors, UpdateCvTemplate $update): AdminCvTemplateResource
    {
        return new AdminCvTemplateResource($update->execute($actors->fromRequest($request), $key, $request->enabled(), $request->isDefault()));
    }
}
