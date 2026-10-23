<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Job\Application\AssignMemberToJobs;
use Src\Job\Application\CloneJob;
use Src\Job\Application\CloseJob;
use Src\Job\Application\CountJobs;
use Src\Job\Application\CreateJob;
use Src\Job\Application\DeleteJob;
use Src\Job\Application\GetJob;
use Src\Job\Application\GetJobAssignees;
use Src\Job\Application\GetJobPerformance;
use Src\Job\Application\ListJobs;
use Src\Job\Application\PreviewJob;
use Src\Job\Application\PublishJob;
use Src\Job\Application\ReopenJob;
use Src\Job\Application\ReplaceJobAssignees;
use Src\Job\Application\SetHiringManagerBoundary;
use Src\Job\Application\UpdateJob;
use Src\Job\Interface\Http\Requests\AssignMemberToJobsRequest;
use Src\Job\Interface\Http\Requests\CountJobsRequest;
use Src\Job\Interface\Http\Requests\ListJobsRequest;
use Src\Job\Interface\Http\Requests\ReopenJobRequest;
use Src\Job\Interface\Http\Requests\ReplaceJobAssigneesRequest;
use Src\Job\Interface\Http\Requests\SaveJobRequest;
use Src\Job\Interface\Http\Requests\SetHiringManagerBoundaryRequest;
use Src\Job\Interface\Http\Resources\JobDetailResource;
use Src\Job\Interface\Http\Resources\JobListResource;
use Src\Job\Interface\Http\Resources\JobPerformanceResource;
use Src\Job\Interface\Http\Resources\JobStatusCountsResource;
use Src\Job\Interface\Http\Resources\PublicJobResource;
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
        $page = $list->execute($actors->fromRequest($request), $request->status(), $request->search(), $request->assignedToMe(), $request->sort(), $request->pageRequest(), $request->deadlineTo(), $request->withoutApplications(), $request->extraFilters());

        return JobListResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function statusCounts(CountJobsRequest $request, ActorResolver $actors, CountJobs $count): JobStatusCountsResource
    {
        return new JobStatusCountsResource($count->execute($actors->fromRequest($request), $request->search(), $request->assignedToMe(), $request->deadlineTo(), $request->withoutApplications(), $request->extraFilters()));
    }

    public function show(Request $request, int $id, RequestContext $context, ActorResolver $actors, GetJob $get): JobDetailResource
    {
        return new JobDetailResource($get->execute($actors->fromRequest($request), $id, $context->language));
    }

    /**
     * The job as the public page will show it, in any status, from the saved fields. `meta.publish_check` says whether publishing would pass and, if not, which fields (by API name) are
     * missing. Nothing is counted and the answer is never cached.
     *
     * @response array{data: \Src\Job\Interface\Http\Resources\PublicJobResource, meta: array{publish_check: array{ready: bool, missing: list<string>}}}
     */
    public function preview(Request $request, int $id, RequestContext $context, ActorResolver $actors, PreviewJob $preview): JsonResponse
    {
        $result = $preview->execute($actors->fromRequest($request), $id, $context->language);

        return (new PublicJobResource($result['view']))
            ->additional(['meta' => ['publish_check' => ['ready' => $result['ready'], 'missing' => $result['missing']]]])
            ->response()
            ->header('Cache-Control', 'no-store, private');
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
        $replace->execute($actor, $id, $request->assignees(), $request->ip(), $request->userAgent());

        return new JsonResponse(['data' => $get->execute($actor, $id)]);
    }

    /** Adds the member to many jobs of the company at once, as a recruiter; the answer says how many were added and how many already had the member. */
    public function assignMember(AssignMemberToJobsRequest $request, int $userId, ActorResolver $actors, AssignMemberToJobs $assign): JsonResponse
    {
        return new JsonResponse(['data' => $assign->execute($actors->fromRequest($request), $userId, $request->jobIds(), $request->ip(), $request->userAgent())]);
    }

    public function setHiringManagerBoundary(SetHiringManagerBoundaryRequest $request, int $id, ActorResolver $actors, SetHiringManagerBoundary $set): Response
    {
        $set->execute($actors->fromRequest($request), $id, $request->stageId());

        return response()->noContent();
    }
}
