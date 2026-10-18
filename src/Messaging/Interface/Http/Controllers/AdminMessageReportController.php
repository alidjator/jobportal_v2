<?php

namespace Src\Messaging\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Messaging\Application\Reports\DecideMessageReport;
use Src\Messaging\Application\Reports\GetMessageReport;
use Src\Messaging\Application\Reports\ListMessageReports;
use Src\Messaging\Domain\ReportStatus;
use Src\Messaging\Interface\Http\Requests\DecideMessageReportRequest;
use Src\Messaging\Interface\Http\Requests\ListMessageReportsRequest;
use Src\Messaging\Interface\Http\Resources\AdminMessageReportDetailResource;
use Src\Messaging\Interface\Http\Resources\AdminMessageReportResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\PaginatedResources;

/** Conversation reports for admins holding `messages.moderate`. */
final class AdminMessageReportController extends ApiController
{
    public function index(ListMessageReportsRequest $request, ListMessageReports $list): AnonymousResourceCollection
    {
        return AdminMessageReportResource::collection(PaginatedResources::paginator($list->execute($request->status(), $request->pageRequest()), $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetMessageReport $get): AdminMessageReportDetailResource
    {
        return new AdminMessageReportDetailResource($get->execute($actors->fromRequest($request), $id));
    }

    public function dismiss(DecideMessageReportRequest $request, int $id, ActorResolver $actors, DecideMessageReport $decide, GetMessageReport $get): AdminMessageReportDetailResource
    {
        $decide->execute($actors->fromRequest($request), $id, ReportStatus::Dismissed, $request->note(), $request->ip(), $request->userAgent());

        return new AdminMessageReportDetailResource($get->execute($actors->fromRequest($request), $id));
    }

    public function action(DecideMessageReportRequest $request, int $id, ActorResolver $actors, DecideMessageReport $decide, GetMessageReport $get): AdminMessageReportDetailResource
    {
        $decide->execute($actors->fromRequest($request), $id, ReportStatus::Actioned, $request->note(), $request->ip(), $request->userAgent());

        return new AdminMessageReportDetailResource($get->execute($actors->fromRequest($request), $id));
    }
}
