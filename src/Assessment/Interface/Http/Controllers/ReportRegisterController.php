<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Assessment\Application\Register\ExportReportRegister;
use Src\Assessment\Application\Register\ListReportRegister;
use Src\Assessment\Interface\Http\Requests\ExportRegisterRequest;
use Src\Assessment\Interface\Http\Requests\ListRegisterRequest;
use Src\Assessment\Interface\Http\Resources\RegisterItemResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CsvResponse;
use Src\Shared\Interface\Http\PaginatedResources;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The report register of the psychologist's provider: the signed and automatically issued reports, and the same as a file. */
final class ReportRegisterController extends ApiController
{
    public function index(ListRegisterRequest $request, ActorResolver $actors, ListReportRegister $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->registerFilter(), $request->pageRequest());

        return RegisterItemResource::collection(PaginatedResources::paginator($page, $request));
    }

    public function export(ExportRegisterRequest $request, ActorResolver $actors, ExportReportRegister $export): StreamedResponse
    {
        $file = $export->execute($actors->fromRequest($request), $request->registerFilter(), $request->ip(), $request->userAgent());

        return CsvResponse::of($file->filename, $file->headers, $file->rows);
    }
}
