<?php

namespace Src\JobSeeker\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\JobSeeker\Application\ImportSummary\DismissMyImportSummary;
use Src\JobSeeker\Application\ImportSummary\GetMyImportSummary;
use Src\JobSeeker\Interface\Http\Resources\ImportSummaryResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** What the karir.pkp import did and did not carry for the caller. */
final class ImportSummaryController extends ApiController
{
    /** `404 no-import-summary` when the account has none (it was not imported) or it was purged. */
    public function show(Request $request, ActorResolver $actors, GetMyImportSummary $get): ImportSummaryResource
    {
        return new ImportSummaryResource($get->execute($actors->fromRequest($request)));
    }

    /** Closes the card; the full list stays readable. `404 no-import-summary` when there is none. */
    public function dismiss(Request $request, ActorResolver $actors, DismissMyImportSummary $dismiss): Response
    {
        $dismiss->execute($actors->fromRequest($request));

        return response()->noContent();
    }
}
