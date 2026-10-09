<?php

namespace Src\Dashboard\Interface\Http\Controllers;

use Illuminate\Http\Response;
use Src\Dashboard\Application\GetAnalytics;
use Src\Dashboard\Interface\Http\AnalyticsCsv;
use Src\Dashboard\Interface\Http\Requests\AnalyticsRequest;
use Src\Dashboard\Interface\Http\Resources\AnalyticsResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The analyses over a date range: for a company admin about their own company, for an admin with `analytics.view` about the platform. Read only. */
final class AnalyticsController extends ApiController
{
    public function show(AnalyticsRequest $request, string $report, ActorResolver $actors, GetAnalytics $get): AnalyticsResource|Response
    {
        $view = $get->execute($actors->fromRequest($request), $report, $request->from(), $request->to());

        return $request->wantsCsv() ? AnalyticsCsv::of($view, $request->table()) : new AnalyticsResource($view);
    }
}
