<?php

namespace Src\JobSeeker\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\JobSeeker\Application\Export\GetMyDataExport;
use Src\JobSeeker\Application\Export\GetMyDataExportFile;
use Src\JobSeeker\Application\Export\RequestDataExport;
use Src\JobSeeker\Interface\Http\Resources\DataExportResource;
use Src\Shared\Application\Clock;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** The job seeker's copy of their personal data (UU PDP): ask for it, see how it is going, download it. */
final class DataExportController extends ApiController
{
    /** Queues the build; answers `202` with the new request. `409 data-export-running` while one is building, `429 data-export-limit` for a second one within 24 hours. */
    public function store(Request $request, ActorResolver $actors, RequestDataExport $requestExport): JsonResponse
    {
        return (new DataExportResource($requestExport->execute($actors->fromRequest($request))))->response()->setStatusCode(JsonResponse::HTTP_ACCEPTED);
    }

    /** The latest request: its status and, while it is ready, the download link. `404` when none was ever made. */
    public function show(Request $request, ActorResolver $actors, GetMyDataExport $get): DataExportResource
    {
        return new DataExportResource($get->execute($actors->fromRequest($request)));
    }

    /** The ZIP archive of the latest request, while it is ready and within its 24 hours. */
    public function download(Request $request, ActorResolver $actors, GetMyDataExportFile $get, Clock $clock): Response
    {
        $bytes = $get->execute($actors->fromRequest($request));
        $name = 'personal-data-'.$clock->now()->format('Y-m-d').'.zip';

        return new Response($bytes, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $name, $name),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
