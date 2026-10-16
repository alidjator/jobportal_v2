<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Job\Application\CloneJob;
use Src\Job\Application\CloseJob;
use Src\Job\Application\CreateJob;
use Src\Job\Application\DeleteJob;
use Src\Job\Application\GetJob;
use Src\Job\Application\GetJobAssignees;
use Src\Job\Application\GetJobPerformance;
use Src\Job\Application\ListJobs;
use Src\Job\Application\PublishJob;
use Src\Job\Application\ReopenJob;
use Src\Job\Application\ReplaceJobAssignees;
use Src\Job\Application\UpdateJob;
use Src\Job\Interface\Http\Requests\ListJobsRequest;
use Src\Job\Interface\Http\Requests\ReopenJobRequest;
use Src\Job\Interface\Http\Requests\ReplaceJobAssigneesRequest;
use Src\Job\Interface\Http\Requests\SaveJobRequest;
use Src\Job\Interface\Http\Resources\JobDetailResource;
use Src\Job\Interface\Http\Resources\JobListResource;
use Src\Job\Interface\Http\Resources\JobPerformanceResource;
use Src\Shared\Application\RequestContext;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/**
 * The jobs of the caller's own company. A company admin sees and manages every job; a recruiter only the jobs assigned to them (any other job is a 404).
 * The use cases check the caller; the assignees can be changed by company admins only. Every action that changes a job answers with the whole job.
 */
final class CompanyJobController extends ApiController
{
    public function index(ListJobsRequest $request, ActorResolver $actors, ListJobs $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->status(), $request->search(), $request->assignedToMe(), $request->sort(), $request->pageRequest(), $request->deadlineTo(), $request->withoutApplications());

        return JobListResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function show(Request $request, int $id, RequestContext $context, ActorResolver $actors, GetJob $get): JobDetailResource
    {
        return new JobDetailResource($get->execute($actors->fromRequest($request), $id, $context->language));
    }

    public function performance(Request $request, int $id, ActorResolver $actors, GetJobPerformance $get): JobPerformanceResource
    {
        return new JobPerformanceResource($get->execute($actors->fromRequest($request), $id));
    }

    public function store(SaveJobRequest $request, RequestContext $context, ActorResolver $actors, CreateJob $create, GetJob $get): JsonResponse
    {
        $actor = $actors->fromRequest($request);
        $id = $create->execute($actor, $request->title(), $request->fields(), $request->requisitionId());

        return (new JobDetailResource($get->execute($actor, $id, $context->language)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(SaveJobRequest $request, int $id, RequestContext $context, ActorResolver $actors, UpdateJob $update, GetJob $get): JobDetailResource
    {
        $actor = $actors->fromRequest($request);
        $update->execute($actor, $id, $request->changes(), $request->ip(), $request->userAgent());

        return new JobDetailResource($get->execute($actor, $id, $context->language));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteJob $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }

    public function publish(Request $request, int $id, RequestContext $context, ActorResolver $actors, PublishJob $publish, GetJob $get): JobDetailResource
    {
        $actor = $actors->fromRequest($request);
        $publish->execute($actor, $id, $request->ip(), $request->userAgent());

        return new JobDetailResource($get->execute($actor, $id, $context->language));
    }

    public function close(Request $request, int $id, RequestContext $context, ActorResolver $actors, CloseJob $close, GetJob $get): JobDetailResource
    {
        $actor = $actors->fromRequest($request);
        $close->execute($actor, $id, $request->ip(), $request->userAgent());

        return new JobDetailResource($get->execute($actor, $id, $context->language));
    }

    public function reopen(ReopenJobRequest $request, int $id, RequestContext $context, ActorResolver $actors, ReopenJob $reopen, GetJob $get): JobDetailResource
    {
        $actor = $actors->fromRequest($request);
        $reopen->execute($actor, $id, $request->deadline(), $request->ip(), $request->userAgent());

        return new JobDetailResource($get->execute($actor, $id, $context->language));
    }

    public function clone(Request $request, int $id, RequestContext $context, ActorResolver $actors, CloneJob $clone, GetJob $get): JsonResponse
    {
        $actor = $actors->fromRequest($request);
        $copyId = $clone->execute($actor, $id);

        return (new JobDetailResource($get->execute($actor, $copyId, $context->language)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function assignees(Request $request, int $id, ActorResolver $actors, GetJobAssignees $get): JsonResponse
    {
        return new JsonResponse(['data' => $get->execute($actors->fromRequest($request), $id)]);
    }

    public function replaceAssignees(ReplaceJobAssigneesRequest $request, int $id, ActorResolver $actors, ReplaceJobAssignees $replace, GetJobAssignees $get): JsonResponse
    {
        $actor = $actors->fromRequest($request);
        $replace->execute($actor, $id, $request->userIds(), $request->ip(), $request->userAgent());

        return new JsonResponse(['data' => $get->execute($actor, $id)]);
    }
}
