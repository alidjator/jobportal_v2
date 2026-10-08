<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\ExportJobApplications;
use Src\Recruitment\Application\GetCompanyApplication;
use Src\Recruitment\Application\GetCompanyApplicationResume;
use Src\Recruitment\Application\ListJobApplications;
use Src\Recruitment\Application\ProcessApplication;
use Src\Recruitment\Application\StageCount;
use Src\Recruitment\Interface\Http\CsvDownload;
use Src\Recruitment\Interface\Http\PdfDownload;
use Src\Recruitment\Interface\Http\Requests\ExportJobApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\ListJobApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\MoveApplicationRequest;
use Src\Recruitment\Interface\Http\Requests\RejectApplicationRequest;
use Src\Recruitment\Interface\Http\Resources\CompanyApplicationDetailResource;
use Src\Recruitment\Interface\Http\Resources\CompanyApplicationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** The applications of the caller's company, for the members who may see the job (a company admin: every job; a recruiter: the jobs assigned to them). */
final class CompanyApplicationController extends ApiController
{
    public function index(ListJobApplicationsRequest $request, int $id, ActorResolver $actors, ListJobApplications $list): AnonymousResourceCollection
    {
        $result = $list->execute($actors->fromRequest($request), $request->toQuery($id), $request->pageRequest());

        return PaginatedResources::of($result->page, CompanyApplicationResource::class, $request)->additional([
            'stages' => array_map(static fn (StageCount $stage): array => ['id' => $stage->stageId, 'type' => $stage->type->value, 'name' => $stage->name, 'count' => $stage->count], $result->stages),
        ]);
    }

    public function export(ExportJobApplicationsRequest $request, int $id, ActorResolver $actors, ExportJobApplications $export): Response
    {
        return CsvDownload::of($export->execute($actors->fromRequest($request), $request->toQuery($id), $request->ip(), $request->userAgent()));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetCompanyApplication $get): CompanyApplicationDetailResource
    {
        return new CompanyApplicationDetailResource($get->execute($actors->fromRequest($request), $id));
    }

    public function resume(Request $request, int $id, ActorResolver $actors, GetCompanyApplicationResume $get): Response
    {
        return PdfDownload::of($get->execute($actors->fromRequest($request), $id));
    }

    public function move(MoveApplicationRequest $request, int $id, ActorResolver $actors, ProcessApplication $process): Response
    {
        $process->move($actors->fromRequest($request), $id, $request->stageId(), $request->interview(), $request->offer());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function reject(RejectApplicationRequest $request, int $id, ActorResolver $actors, ProcessApplication $process): Response
    {
        $process->reject($actors->fromRequest($request), $id, $request->reason(), $request->note());

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
