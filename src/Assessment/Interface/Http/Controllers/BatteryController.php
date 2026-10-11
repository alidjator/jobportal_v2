<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Assessment\Application\Authoring\GetNorms;
use Src\Assessment\Application\Authoring\GetReportTemplate;
use Src\Assessment\Application\Authoring\ReplaceNorms;
use Src\Assessment\Application\Authoring\SaveReportTemplate;
use Src\Assessment\Interface\Http\Requests\NormsRequest;
use Src\Assessment\Interface\Http\Requests\ReportTemplateRequest;
use Src\Assessment\Interface\Http\Resources\NormResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The norm table and the report template of a battery, for the psychologists of its provider. */
final class BatteryController extends ApiController
{
    public function norms(Request $request, int $id, ActorResolver $actors, GetNorms $get): AnonymousResourceCollection
    {
        return NormResource::collection($get->execute($actors->fromRequest($request), $id));
    }

    public function replaceNorms(NormsRequest $request, int $id, ActorResolver $actors, ReplaceNorms $replace): AnonymousResourceCollection
    {
        return NormResource::collection($replace->execute($actors->fromRequest($request), $id, $request->norms()));
    }

    public function reportTemplate(Request $request, int $id, ActorResolver $actors, GetReportTemplate $get): JsonResponse
    {
        return response()->json(['data' => ['body' => $get->execute($actors->fromRequest($request), $id)]]);
    }

    public function saveReportTemplate(ReportTemplateRequest $request, int $id, ActorResolver $actors, SaveReportTemplate $save): JsonResponse
    {
        return response()->json(['data' => ['body' => $save->execute($actors->fromRequest($request), $id, $request->body())]]);
    }
}
