<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Job\Application\Reports\DismissJobReports;
use Src\Job\Application\Reports\ListReportedJobs;
use Src\Job\Application\Reports\ListReportsOfJob;
use Src\Job\Interface\Http\Requests\ListJobReportsRequest;
use Src\Job\Interface\Http\Resources\JobReportEntryResource;
use Src\Job\Interface\Http\Resources\ReportedJobResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The reports about jobs, for platform admins holding `jobs.manage`. */
final class AdminJobReportController extends ApiController
{
    public function index(ListJobReportsRequest $request, ActorResolver $actors, ListReportedJobs $list): AnonymousResourceCollection
    {
        return ReportedJobResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $request->pageRequest()), $request));
    }

    public function ofJob(ListJobReportsRequest $request, int $id, ActorResolver $actors, ListReportsOfJob $list): AnonymousResourceCollection
    {
        return JobReportEntryResource::collection(PaginatedResources::paginator($list->execute($actors->fromRequest($request), $id, $request->pageRequest()), $request));
    }

    public function dismiss(Request $request, int $id, ActorResolver $actors, DismissJobReports $dismiss): Response
    {
        $dismiss->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
