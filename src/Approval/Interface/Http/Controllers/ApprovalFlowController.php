<?php

namespace Src\Approval\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Approval\Application\Flow\ApprovalFlows;
use Src\Approval\Interface\Http\Requests\SaveApprovalFlowRequest;
use Src\Approval\Interface\Http\Resources\ApprovalFlowResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The approval flows of the signed-in member's group (`group.approvals.manage` changes them) and of their tenant (`approvals.manage` changes them). */
final class ApprovalFlowController extends ApiController
{
    public function groupIndex(Request $request, ActorResolver $actors, ApprovalFlows $flows): AnonymousResourceCollection
    {
        return ApprovalFlowResource::collection($flows->ofGroup($actors->fromRequest($request)));
    }

    public function groupSave(SaveApprovalFlowRequest $request, string $subjectType, ActorResolver $actors, ApprovalFlows $flows): AnonymousResourceCollection
    {
        $flows->saveForGroup($actors->fromRequest($request), $subjectType, $request->flow(), $request->ip(), $request->userAgent());

        return ApprovalFlowResource::collection($flows->ofGroup($actors->fromRequest($request)));
    }

    public function groupRemove(Request $request, string $subjectType, ActorResolver $actors, ApprovalFlows $flows): Response
    {
        $flows->removeForGroup($actors->fromRequest($request), $subjectType, $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function companyIndex(Request $request, ActorResolver $actors, ApprovalFlows $flows): AnonymousResourceCollection
    {
        return ApprovalFlowResource::collection($flows->ofCompany($actors->fromRequest($request)));
    }

    public function companySave(SaveApprovalFlowRequest $request, string $subjectType, ActorResolver $actors, ApprovalFlows $flows): AnonymousResourceCollection
    {
        $flows->saveForCompany($actors->fromRequest($request), $subjectType, $request->flow(), $request->ip(), $request->userAgent());

        return ApprovalFlowResource::collection($flows->ofCompany($actors->fromRequest($request)));
    }

    public function companyRemove(Request $request, string $subjectType, ActorResolver $actors, ApprovalFlows $flows): Response
    {
        $flows->removeForCompany($actors->fromRequest($request), $subjectType, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
