<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Assessment\Application\Licence\DecideLicence;
use Src\Assessment\Application\Licence\GetLicenceDocument;
use Src\Assessment\Application\Licence\ListLicenceSubmissions;
use Src\Assessment\Interface\Http\AttachmentResponse;
use Src\Assessment\Interface\Http\Requests\ListLicenceSubmissionsRequest;
use Src\Assessment\Interface\Http\Requests\RejectLicenceRequest;
use Src\Assessment\Interface\Http\Resources\LicenceQueueItemResource;
use Src\Assessment\Interface\Http\Resources\LicenceSubmissionResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The licence submissions of the psychologists, for platform admins holding `assessment_providers.manage`. */
final class AdminLicenceController extends ApiController
{
    public function index(ListLicenceSubmissionsRequest $request, ActorResolver $actors, ListLicenceSubmissions $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->status(), $request->pageRequest());

        return LicenceQueueItemResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function document(Request $request, int $id, ActorResolver $actors, GetLicenceDocument $get): Response
    {
        return AttachmentResponse::of($get->execute($actors->fromRequest($request), $id));
    }

    public function approve(Request $request, int $id, ActorResolver $actors, DecideLicence $decide): LicenceSubmissionResource
    {
        return new LicenceSubmissionResource($decide->approve($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }

    public function reject(RejectLicenceRequest $request, int $id, ActorResolver $actors, DecideLicence $decide): LicenceSubmissionResource
    {
        return new LicenceSubmissionResource($decide->reject($actors->fromRequest($request), $id, $request->note(), $request->ip(), $request->userAgent()));
    }
}
