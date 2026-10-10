<?php

namespace Src\Review\Interface\Http\Controllers;

use Illuminate\Http\Response;
use Src\Review\Application\ReportReview;
use Src\Review\Interface\Http\Requests\ReportRequest;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** Reporting a published review: a job seeker through `reviews/{id}/report`, a company admin through `company/reviews/{id}/report`; the actor decides who reports. */
final class ReportController extends ApiController
{
    public function store(ReportRequest $request, int $id, ActorResolver $actors, ReportReview $report): Response
    {
        $report->execute($actors->fromRequest($request), $id, $request->reason(), $request->note());

        return response()->noContent();
    }
}
