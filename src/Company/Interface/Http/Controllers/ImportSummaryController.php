<?php

namespace Src\Company\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Company\Application\ImportSummary\DismissCompanyImportSummary;
use Src\Company\Application\ImportSummary\GetCompanyImportSummary;
use Src\Company\Interface\Http\Resources\ImportSummaryResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** What the karir.pkp import did and did not carry for the caller's company (company admins). */
final class ImportSummaryController extends ApiController
{
    /** `404 no-import-summary` when the company has none (it was not imported) or it was purged. */
    public function show(Request $request, ActorResolver $actors, GetCompanyImportSummary $get): ImportSummaryResource
    {
        return new ImportSummaryResource($get->execute($actors->fromRequest($request)));
    }

    /** Closes the card for the company; the full list stays readable. `404 no-import-summary` when there is none. */
    public function dismiss(Request $request, ActorResolver $actors, DismissCompanyImportSummary $dismiss): Response
    {
        $dismiss->execute($actors->fromRequest($request));

        return response()->noContent();
    }
}
