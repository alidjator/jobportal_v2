<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Job\Application\Admin\ApproveJob;
use Src\Job\Application\Admin\GetJobForAdmin;
use Src\Job\Application\Admin\ListJobsForAdmin;
use Src\Job\Application\Admin\RejectJob;
use Src\Job\Application\Admin\RestoreJob;
use Src\Job\Application\Admin\TakeDownJob;
use Src\Job\Interface\Http\Requests\JobDecisionReasonRequest;
use Src\Job\Interface\Http\Requests\ListJobsForAdminRequest;
use Src\Job\Interface\Http\Resources\AdminJobDetailResource;
use Src\Job\Interface\Http\Resources\AdminJobListResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The jobs of every company for platform admins holding `jobs.manage`. A decision answers with the whole job as it is afterwards. */
final class AdminJobController extends ApiController
{
    public function index(ListJobsForAdminRequest $request, ActorResolver $actors, ListJobsForAdmin $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->status(), $request->companyId(), $request->search(), $request->sort(), $request->pageRequest());

        return AdminJobListResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, RequestContext $context, ActorResolver $actors, GetJobForAdmin $get): AdminJobDetailResource
    {
        return new AdminJobDetailResource($get->execute($actors->fromRequest($request), $id, $context->language));
    }

    public function approve(Request $request, int $id, RequestContext $context, ActorResolver $actors, ApproveJob $approve, GetJobForAdmin $get): AdminJobDetailResource
    {
        $actor = $actors->fromRequest($request);
        $approve->execute($actor, $id, $request->ip(), $request->userAgent());

        return new AdminJobDetailResource($get->execute($actor, $id, $context->language));
    }

    public function reject(JobDecisionReasonRequest $request, int $id, RequestContext $context, ActorResolver $actors, RejectJob $reject, GetJobForAdmin $get): AdminJobDetailResource
    {
        $actor = $actors->fromRequest($request);
        $reject->execute($actor, $id, $request->reason(), $request->ip(), $request->userAgent());

        return new AdminJobDetailResource($get->execute($actor, $id, $context->language));
    }

    public function takeDown(JobDecisionReasonRequest $request, int $id, RequestContext $context, ActorResolver $actors, TakeDownJob $takeDown, GetJobForAdmin $get): AdminJobDetailResource
    {
        $actor = $actors->fromRequest($request);
        $takeDown->execute($actor, $id, $request->reason(), $request->ip(), $request->userAgent());

        return new AdminJobDetailResource($get->execute($actor, $id, $context->language));
    }

    public function restore(Request $request, int $id, RequestContext $context, ActorResolver $actors, RestoreJob $restore, GetJobForAdmin $get): AdminJobDetailResource
    {
        $actor = $actors->fromRequest($request);
        $restore->execute($actor, $id, $request->ip(), $request->userAgent());

        return new AdminJobDetailResource($get->execute($actor, $id, $context->language));
    }
}
