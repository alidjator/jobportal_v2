<?php

namespace Src\Approval\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Approval\Application\Instance\MyApprovals;
use Src\Approval\Application\Instance\ReplaceApprover;
use Src\Approval\Interface\Http\Requests\ApprovalReasonRequest;
use Src\Approval\Interface\Http\Requests\ReplaceApproverRequest;
use Src\Approval\Interface\Http\Resources\ApprovalResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The approvals of the signed-in account: the ones waiting for it (`?as=approver`, the default) or that it asked for (`?as=requester`), and its decisions. */
final class ApprovalController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, MyApprovals $approvals): AnonymousResourceCollection
    {
        $as = $request->query('as') === 'requester' ? 'requester' : 'approver';

        return ApprovalResource::collection($approvals->mine($actors->fromRequest($request), $as));
    }

    public function show(Request $request, int $id, ActorResolver $actors, MyApprovals $approvals): ApprovalResource
    {
        return new ApprovalResource($approvals->get($actors->fromRequest($request), $id));
    }

    public function approve(Request $request, int $id, ActorResolver $actors, MyApprovals $approvals): ApprovalResource
    {
        return new ApprovalResource($approvals->approve($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }

    public function reject(ApprovalReasonRequest $request, int $id, ActorResolver $actors, MyApprovals $approvals): ApprovalResource
    {
        return new ApprovalResource($approvals->reject($actors->fromRequest($request), $id, $request->reason(), $request->ip(), $request->userAgent()));
    }

    public function returnForRevision(ApprovalReasonRequest $request, int $id, ActorResolver $actors, MyApprovals $approvals): ApprovalResource
    {
        return new ApprovalResource($approvals->returnForRevision($actors->fromRequest($request), $id, $request->reason(), $request->ip(), $request->userAgent()));
    }

    public function replace(ReplaceApproverRequest $request, ActorResolver $actors, ReplaceApprover $replace, MyApprovals $approvals): ApprovalResource
    {
        $id = (int) $request->route('id');
        $actor = $actors->fromRequest($request);
        $replace->execute($actor, $id, (int) $request->route('slotId'), $request->userId(), $request->ip(), $request->userAgent());

        return new ApprovalResource($approvals->get($actor, $id));
    }
}
