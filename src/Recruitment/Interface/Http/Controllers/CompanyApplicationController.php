<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\ExportHires;
use Src\Recruitment\Application\ExportJobApplications;
use Src\Recruitment\Application\GetCompanyApplication;
use Src\Recruitment\Application\GetCompanyApplicationResume;
use Src\Recruitment\Application\JobApplicationList;
use Src\Recruitment\Application\ListCompanyApplications;
use Src\Recruitment\Application\ListJobApplications;
use Src\Recruitment\Application\ProcessApplication;
use Src\Recruitment\Application\StageCount;
use Src\Recruitment\Application\UpdateApplicationHire;
use Src\Recruitment\Application\WithdrawApplicationByTeam;
use Src\Recruitment\Interface\Http\CsvDownload;
use Src\Recruitment\Interface\Http\PdfDownload;
use Src\Recruitment\Interface\Http\Requests\ExportHiresRequest;
use Src\Recruitment\Interface\Http\Requests\ExportJobApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\ListCompanyApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\ListJobApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\MarkApplicationWithdrawnRequest;
use Src\Recruitment\Interface\Http\Requests\MoveApplicationRequest;
use Src\Recruitment\Interface\Http\Requests\RejectApplicationRequest;
use Src\Recruitment\Interface\Http\Requests\UpdateApplicationHireRequest;
use Src\Recruitment\Interface\Http\Resources\CompanyApplicantResource;
use Src\Recruitment\Interface\Http\Resources\CompanyApplicationDetailResource;
use Src\Recruitment\Interface\Http\Resources\CompanyApplicationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The applications of the caller's company, for the members who may see the job (a company admin: every job; a recruiter: the jobs assigned to them). */
final class CompanyApplicationController extends ApiController
{
    public function index(ListJobApplicationsRequest $request, int $id, ActorResolver $actors, ListJobApplications $list): AnonymousResourceCollection
    {
        $result = $list->execute($actors->fromRequest($request), $request->toQuery($id), $request->pageRequest());

        return CompanyApplicationResource::collection(PaginatedResources::paginator($result->page, $request))->additional(['stages' => $this->stages($result)]);
    }

    /** The applicants across the jobs the member may see (`docs/design/phase-8-applicant-search.md` section 4): each row is the card of the job list plus its job. */
    public function all(ListCompanyApplicationsRequest $request, ActorResolver $actors, ListCompanyApplications $list): AnonymousResourceCollection
    {
        $result = $list->execute($actors->fromRequest($request), $request->toQuery(), $request->pageRequest());

        return CompanyApplicantResource::collection(PaginatedResources::paginator($result->page, $request))->additional(['stages' => $this->stages($result)]);
    }

    /** @return list<array{id: int, type: string, name: string, count: int}> */
    private function stages(JobApplicationList $result): array
    {
        return array_map(static fn (StageCount $stage): array => ['id' => $stage->stageId, 'type' => $stage->type->value, 'name' => $stage->name, 'count' => $stage->count], $result->stages);
    }

    public function export(ExportJobApplicationsRequest $request, int $id, ActorResolver $actors, ExportJobApplications $export): StreamedResponse
    {
        return CsvDownload::of($export->execute($actors->fromRequest($request), $request->toQuery($id), $request->ip(), $request->userAgent()));
    }

    /** The hires of a period across the jobs the member may see, as a CSV (`docs/design/phase-8-hiring.md` section 3). */
    public function exportHires(ExportHiresRequest $request, ActorResolver $actors, ExportHires $export): StreamedResponse
    {
        return CsvDownload::ofHires($export->execute($actors->fromRequest($request), $request->fromDay(), $request->toDay(), $request->jobId(), $request->ip(), $request->userAgent()));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetCompanyApplication $get): CompanyApplicationDetailResource
    {
        return new CompanyApplicationDetailResource($get->execute($actors->fromRequest($request), $id));
    }

    public function resume(Request $request, int $id, ActorResolver $actors, GetCompanyApplicationResume $get): Response
    {
        return PdfDownload::of($get->execute($actors->fromRequest($request), $id, $request->ip(), $request->userAgent()));
    }

    /** Answers `meta.job_filled`: true when this hire brought the job's hires up to its vacancies, so the client offers to close the job and reject the rest. */
    public function move(MoveApplicationRequest $request, int $id, ActorResolver $actors, ProcessApplication $process): JsonResponse
    {
        $filled = $process->move($actors->fromRequest($request), $id, $request->stageId(), $request->interview(), $request->offer(), $request->assessment(), $request->scheduleLater(), $request->hire());

        return response()->json(['meta' => ['job_filled' => $filled]]);
    }

    public function reject(RejectApplicationRequest $request, int $id, ActorResolver $actors, ProcessApplication $process): Response
    {
        $process->reject($actors->fromRequest($request), $id, $request->reason(), $request->note());

        return response()->noContent();
    }

    public function updateHire(UpdateApplicationHireRequest $request, int $id, ActorResolver $actors, UpdateApplicationHire $update): Response
    {
        $update->execute($actors->fromRequest($request), $id, $request->change(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function withdraw(MarkApplicationWithdrawnRequest $request, int $id, ActorResolver $actors, WithdrawApplicationByTeam $withdraw): Response
    {
        $withdraw->execute($actors->fromRequest($request), $id, $request->reason(), $request->note(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
