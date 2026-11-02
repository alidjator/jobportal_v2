<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Assessment\Application\Example\DeleteExampleReport;
use Src\Assessment\Application\Example\GetExampleReport;
use Src\Assessment\Application\Example\SaveExampleReport;
use Src\Assessment\Interface\Http\Requests\ExampleReportRequest;
use Src\Assessment\Interface\Http\Resources\ExampleReportResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The example report of a battery, for the psychologists of its provider: what it holds, a new upload (it replaces the old one) and removal. */
final class ExampleReportController extends ApiController
{
    /** `data` is null while the battery has no example report. */
    public function show(Request $request, int $id, ActorResolver $actors, GetExampleReport $get): JsonResponse
    {
        $example = $get->execute($actors->fromRequest($request), $id);

        return response()->json(['data' => $example === null ? null : (new ExampleReportResource($example))->toArray($request)]);
    }

    public function store(ExampleReportRequest $request, int $id, ActorResolver $actors, SaveExampleReport $save): JsonResponse
    {
        $example = $save->execute($actors->fromRequest($request), $id, $request->contents(), $request->originalName());

        return (new ExampleReportResource($example))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteExampleReport $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
