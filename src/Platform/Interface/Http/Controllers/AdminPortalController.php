<?php

namespace Src\Platform\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Platform\Application\Portal\CreatePortalPreview;
use Src\Platform\Application\Portal\DiscardPortalDraft;
use Src\Platform\Application\Portal\GetPortalForAdmin;
use Src\Platform\Application\Portal\PublishPortalIdentity;
use Src\Platform\Application\Portal\SavePortalDraft;
use Src\Platform\Interface\Http\Requests\PortalIdentityRequest;
use Src\Platform\Interface\Http\Resources\AdminPortalResource;
use Src\Shared\Application\Portal\PortalOwner;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\Resources\PortalPreviewResource;

/** The identity of the career site for admins holding `portal.manage`: read it, keep a draft, preview the draft and publish it. */
final class AdminPortalController extends ApiController
{
    public function show(GetPortalForAdmin $get): AdminPortalResource
    {
        return new AdminPortalResource($get->execute());
    }

    public function saveDraft(PortalIdentityRequest $request, ActorResolver $actors, SavePortalDraft $save, GetPortalForAdmin $get): AdminPortalResource
    {
        $save->execute(PortalOwner::platform(), (int) $actors->fromRequest($request)->id, $request->identity());

        return new AdminPortalResource($get->execute());
    }

    /** Throws the draft away; the live identity stays. */
    public function discardDraft(DiscardPortalDraft $discard, GetPortalForAdmin $get): AdminPortalResource
    {
        $discard->execute(PortalOwner::platform());

        return new AdminPortalResource($get->execute());
    }

    /** Makes the draft live after checking it (422 `portal-company-not-live`, `portal-companies-not-in-one-group`, ...). */
    public function publish(Request $request, ActorResolver $actors, PublishPortalIdentity $publish, GetPortalForAdmin $get): AdminPortalResource
    {
        $publish->execute((int) $actors->fromRequest($request)->id, $request->ip(), $request->userAgent());

        return new AdminPortalResource($get->execute());
    }

    public function preview(CreatePortalPreview $preview): PortalPreviewResource
    {
        return new PortalPreviewResource($preview->execute(PortalOwner::platform()));
    }
}
