<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Company\Application\Portal\ManageCompanyPortal;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\Requests\PortalBrandRequest;
use Src\Shared\Interface\Http\Resources\PortalBrandPageResource;
use Src\Shared\Interface\Http\Resources\PortalPreviewResource;

/** The brand of the career site of the signed-in member's own company: a member holding `company.profile` reads it, keeps a draft, previews and publishes it. */
final class CompanyPortalController extends ApiController
{
    public function show(Request $request, ActorResolver $actors, ManageCompanyPortal $portal): PortalBrandPageResource
    {
        return new PortalBrandPageResource($portal->page($actors->fromRequest($request)));
    }

    public function saveDraft(PortalBrandRequest $request, ActorResolver $actors, ManageCompanyPortal $portal): PortalBrandPageResource
    {
        return new PortalBrandPageResource($portal->saveDraft($actors->fromRequest($request), $request->brand()));
    }

    /** Throws the draft away; the live brand stays. */
    public function discardDraft(Request $request, ActorResolver $actors, ManageCompanyPortal $portal): PortalBrandPageResource
    {
        return new PortalBrandPageResource($portal->discardDraft($actors->fromRequest($request)));
    }

    /** Makes the draft the live brand (422 `portal-draft-missing` without a draft). */
    public function publish(Request $request, ActorResolver $actors, ManageCompanyPortal $portal): PortalBrandPageResource
    {
        return new PortalBrandPageResource($portal->publish($actors->fromRequest($request), $request->ip(), $request->userAgent()));
    }

    public function preview(Request $request, ActorResolver $actors, ManageCompanyPortal $portal): PortalPreviewResource
    {
        return new PortalPreviewResource($portal->preview($actors->fromRequest($request)));
    }
}
