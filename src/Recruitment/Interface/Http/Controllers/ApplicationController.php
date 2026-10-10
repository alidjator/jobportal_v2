<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\ApplyToJob;
use Src\Recruitment\Application\GetMyApplication;
use Src\Recruitment\Application\GetMyApplicationResume;
use Src\Recruitment\Application\ListMyApplications;
use Src\Recruitment\Application\WithdrawMyApplication;
use Src\Recruitment\Interface\Http\PdfDownload;
use Src\Recruitment\Interface\Http\Requests\ApplyToJobRequest;
use Src\Recruitment\Interface\Http\Requests\ListMyApplicationsRequest;
use Src\Recruitment\Interface\Http\Requests\WithdrawApplicationRequest;
use Src\Recruitment\Interface\Http\Resources\ApplicationSubmittedResource;
use Src\Recruitment\Interface\Http\Resources\MyApplicationDetailResource;
use Src\Recruitment\Interface\Http\Resources\MyApplicationResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CursorResources;

/** A job seeker's applications. `{job}` is the id, optionally followed by the cosmetic slug, as on the job page. */
final class ApplicationController extends ApiController
{
    public function store(ApplyToJobRequest $request, string $job, ActorResolver $actors, ApplyToJob $apply): JsonResponse
    {
        $application = $apply->execute($actors->fromRequest($request), (int) $job, $request->toInput());

        return (new ApplicationSubmittedResource($application))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function index(ListMyApplicationsRequest $request, ActorResolver $actors, ListMyApplications $list): AnonymousResourceCollection
    {
        $page = $list->execute($actors->fromRequest($request), $request->statusFilter(), $request->cursorRequest());

        return MyApplicationResource::collection($page->items)->additional(CursorResources::envelope($page, $request));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetMyApplication $get): MyApplicationDetailResource
    {
        return new MyApplicationDetailResource($get->execute($actors->fromRequest($request), $id));
    }

    public function withdraw(WithdrawApplicationRequest $request, int $id, ActorResolver $actors, WithdrawMyApplication $withdraw): Response
    {
        $withdraw->execute($actors->fromRequest($request), $id, $request->note());

        return response()->noContent();
    }

    public function resume(Request $request, int $id, ActorResolver $actors, GetMyApplicationResume $get): Response
    {
        return PdfDownload::of($get->execute($actors->fromRequest($request), $id));
    }
}
