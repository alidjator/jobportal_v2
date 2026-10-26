<?php

namespace Src\Workforce\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Workforce\Application\GetWorkforceOptions;
use Src\Workforce\Application\Plan\CreatePlan;
use Src\Workforce\Application\Plan\GetPlan;
use Src\Workforce\Application\Plan\ListPlans;
use Src\Workforce\Application\Plan\PlanSide;
use Src\Workforce\Application\Plan\RevisePlan;
use Src\Workforce\Application\Plan\SubmitPlan;
use Src\Workforce\Application\Plan\UpdatePlan;
use Src\Workforce\Interface\Http\Requests\ListPlansRequest;
use Src\Workforce\Interface\Http\Requests\SavePlanRequest;
use Src\Workforce\Interface\Http\Resources\PlanResource;
use Src\Workforce\Interface\Http\Resources\PlanSummaryResource;
use Src\Workforce\Interface\Http\Resources\WorkforceOptionsResource;

/** The manpower plans of the signed-in member's own tenant (`company/workforce/plans`) or, for a group member, of every legal entity of the group (`organization/workforce/plans`). */
final class PlanController extends ApiController
{
    /** The choices of the plan and requisition forms of the member's own legal entity (positions, units, grades, cost centres, hiring managers); read only. */
    public function options(Request $request, ActorResolver $actors, GetWorkforceOptions $options): WorkforceOptionsResource
    {
        return new WorkforceOptionsResource($options->execute($actors->fromRequest($request)));
    }

    public function companyIndex(ListPlansRequest $request, ActorResolver $actors, ListPlans $list): AnonymousResourceCollection
    {
        return $this->index($request, $actors, $list, PlanSide::Company);
    }

    public function groupIndex(ListPlansRequest $request, ActorResolver $actors, ListPlans $list): AnonymousResourceCollection
    {
        return $this->index($request, $actors, $list, PlanSide::Group);
    }

    public function companyShow(Request $request, int $id, ActorResolver $actors, GetPlan $get): PlanResource
    {
        return new PlanResource($get->execute($actors->fromRequest($request), PlanSide::Company, $id));
    }

    public function groupShow(Request $request, int $id, ActorResolver $actors, GetPlan $get): PlanResource
    {
        return new PlanResource($get->execute($actors->fromRequest($request), PlanSide::Group, $id));
    }

    public function companyStore(SavePlanRequest $request, ActorResolver $actors, CreatePlan $create): JsonResponse
    {
        return $this->store($request, $actors, $create, PlanSide::Company);
    }

    public function groupStore(SavePlanRequest $request, ActorResolver $actors, CreatePlan $create): JsonResponse
    {
        return $this->store($request, $actors, $create, PlanSide::Group);
    }

    public function companyUpdate(SavePlanRequest $request, int $id, ActorResolver $actors, UpdatePlan $update): PlanResource
    {
        return new PlanResource($update->execute($actors->fromRequest($request), PlanSide::Company, $id, $request->lines(), $request->ip(), $request->userAgent()));
    }

    public function groupUpdate(SavePlanRequest $request, int $id, ActorResolver $actors, UpdatePlan $update): PlanResource
    {
        return new PlanResource($update->execute($actors->fromRequest($request), PlanSide::Group, $id, $request->lines(), $request->ip(), $request->userAgent()));
    }

    public function companySubmit(Request $request, int $id, ActorResolver $actors, SubmitPlan $submit): PlanResource
    {
        return new PlanResource($submit->execute($actors->fromRequest($request), PlanSide::Company, $id, $request->ip(), $request->userAgent()));
    }

    public function groupSubmit(Request $request, int $id, ActorResolver $actors, SubmitPlan $submit): PlanResource
    {
        return new PlanResource($submit->execute($actors->fromRequest($request), PlanSide::Group, $id, $request->ip(), $request->userAgent()));
    }

    public function companyRevise(Request $request, int $id, ActorResolver $actors, RevisePlan $revise): JsonResponse
    {
        return (new PlanResource($revise->execute($actors->fromRequest($request), PlanSide::Company, $id, $request->ip(), $request->userAgent())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function groupRevise(Request $request, int $id, ActorResolver $actors, RevisePlan $revise): JsonResponse
    {
        return (new PlanResource($revise->execute($actors->fromRequest($request), PlanSide::Group, $id, $request->ip(), $request->userAgent())))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    private function index(ListPlansRequest $request, ActorResolver $actors, ListPlans $list, PlanSide $side): AnonymousResourceCollection
    {
        return PlanSummaryResource::collection($list->execute($actors->fromRequest($request), $side, $request->legalEntityId(), $request->fiscalYear(), $request->status()));
    }

    private function store(SavePlanRequest $request, ActorResolver $actors, CreatePlan $create, PlanSide $side): JsonResponse
    {
        $view = $create->execute($actors->fromRequest($request), $side, $request->legalEntityId(), $request->fiscalYear(), $request->lines(), $request->ip(), $request->userAgent());

        return (new PlanResource($view))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
