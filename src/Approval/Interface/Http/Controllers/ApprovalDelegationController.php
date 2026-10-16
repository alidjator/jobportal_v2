<?php

namespace Src\Approval\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Approval\Application\Instance\ApprovalDelegations;
use Src\Approval\Interface\Http\Requests\DelegationRequest;
use Src\Approval\Interface\Http\Resources\DelegationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The delegations the signed-in approver gave: another account decides in their place for a date range. */
final class ApprovalDelegationController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ApprovalDelegations $delegations): AnonymousResourceCollection
    {
        return DelegationResource::collection($delegations->mine($actors->fromRequest($request)));
    }

    public function store(DelegationRequest $request, ActorResolver $actors, ApprovalDelegations $delegations): JsonResponse
    {
        $delegation = $delegations->create($actors->fromRequest($request), $request->delegateUserId(), $request->from(), $request->to(), $request->ip(), $request->userAgent());

        return (new DelegationResource($delegation))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, ApprovalDelegations $delegations): Response
    {
        $delegations->remove($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
