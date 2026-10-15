<?php

namespace Src\Company\Interface\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Company\Application\Activity\ExportCompanyActivity;
use Src\Company\Application\Activity\ListCompanyActivity;
use Src\Company\Interface\Http\Requests\ListCompanyActivityRequest;
use Src\Company\Interface\Http\Resources\CompanyActivityResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CsvResponse;
use Src\Shared\Interface\Http\CursorResources;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The activity log of the signed-in company admin's own company, and its CSV export. */
final class CompanyActivityController extends ApiController
{
    public function index(ListCompanyActivityRequest $request, ActorResolver $actors, ListCompanyActivity $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->filters(), $request->cursorRequest());

        return CompanyActivityResource::collection($page->items)->additional(CursorResources::envelope($page, $request));
    }

    public function export(ListCompanyActivityRequest $request, ActorResolver $actors, ExportCompanyActivity $export): StreamedResponse
    {
        $result = $export->execute($actors->fromRequest($request), $request->filters(), $request->ip(), $request->userAgent());
        $name = 'company-activity-'.$result->exportedAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd').'.csv';

        return CsvResponse::of($name, $result->headers, $result->rows);
    }
}
