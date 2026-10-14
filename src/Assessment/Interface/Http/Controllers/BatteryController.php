<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Assessment\Application\Authoring\ConfigureAutoIssue;
use Src\Assessment\Application\Authoring\GetExplanationTemplate;
use Src\Assessment\Application\Authoring\GetNorms;
use Src\Assessment\Application\Authoring\GetReportTemplate;
use Src\Assessment\Application\Authoring\ReplaceNorms;
use Src\Assessment\Application\Authoring\SaveExplanationTemplate;
use Src\Assessment\Application\Authoring\SaveReportTemplate;
use Src\Assessment\Interface\Http\Requests\AutoIssueRequest;
use Src\Assessment\Interface\Http\Requests\NormsRequest;
use Src\Assessment\Interface\Http\Requests\ReportTemplateRequest;
use Src\Assessment\Interface\Http\Resources\NormResource;
use Src\Assessment\Interface\Http\Resources\TestResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The norm table, the report template, the explanation template and the automatic issue of a battery, for the psychologists of its provider. */
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

    public function explanationTemplate(Request $request, int $id, ActorResolver $actors, GetExplanationTemplate $get): JsonResponse
    {
        return response()->json(['data' => ['body' => $get->execute($actors->fromRequest($request), $id)]]);
    }

    public function saveExplanationTemplate(ReportTemplateRequest $request, int $id, ActorResolver $actors, SaveExplanationTemplate $save): JsonResponse
    {
        return response()->json(['data' => ['body' => $save->execute($actors->fromRequest($request), $id, $request->body())]]);
    }

    /** Turns the automatic issue of the battery's reports on or off. */
    public function autoIssue(AutoIssueRequest $request, int $id, ActorResolver $actors, ConfigureAutoIssue $configure): TestResource
    {
        return new TestResource($configure->execute($actors->fromRequest($request), $id, $request->enabled(), $request->responsiblePsychologistId()));
    }

    public function saveReportTemplate(ReportTemplateRequest $request, int $id, ActorResolver $actors, SaveReportTemplate $save): JsonResponse
    {
        return response()->json(['data' => ['body' => $save->execute($actors->fromRequest($request), $id, $request->body())]]);
    }
}
