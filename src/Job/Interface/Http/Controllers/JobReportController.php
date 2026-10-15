<?php

namespace Src\Job\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Src\Job\Application\Reports\ReportJob;
use Src\Job\Interface\Http\Requests\ReportJobRequest;
use Src\Job\Interface\Http\Resources\JobReportReceiptResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** A job seeker reports a job. */
final class JobReportController extends ApiController
{
    public function store(ReportJobRequest $request, int $id, ActorResolver $actors, ReportJob $report): JsonResponse
    {
        $receipt = $report->execute($actors->fromRequest($request), $id, $request->reason(), $request->note());

        return (new JobReportReceiptResource($receipt))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
