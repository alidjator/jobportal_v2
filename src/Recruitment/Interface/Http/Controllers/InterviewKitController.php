<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\CreateInterviewKit;
use Src\Recruitment\Application\DeleteInterviewKit;
use Src\Recruitment\Application\GetInterviewKit;
use Src\Recruitment\Application\ListInterviewKits;
use Src\Recruitment\Application\UpdateInterviewKit;
use Src\Recruitment\Interface\Http\Requests\SaveInterviewKitRequest;
use Src\Recruitment\Interface\Http\Resources\InterviewKitResource;
use Src\Recruitment\Interface\Http\Resources\InterviewKitSummaryResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The interview kit library of the caller's own company; every member reads and changes it. */
final class InterviewKitController extends ApiController
{
    public function index(Request $request, ActorResolver $actors, ListInterviewKits $list): AnonymousResourceCollection
    {
        return InterviewKitSummaryResource::collection($list->execute($actors->fromRequest($request)));
    }

    public function show(Request $request, int $id, ActorResolver $actors, GetInterviewKit $get): InterviewKitResource
    {
        return new InterviewKitResource($get->execute($actors->fromRequest($request), $id));
    }

    public function store(SaveInterviewKitRequest $request, ActorResolver $actors, CreateInterviewKit $create): JsonResponse
    {
        $kit = $create->execute($actors->fromRequest($request), $request->name(), $request->questions());

        return (new InterviewKitResource($kit))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveInterviewKitRequest $request, int $id, ActorResolver $actors, UpdateInterviewKit $update): InterviewKitResource
    {
        return new InterviewKitResource($update->execute($actors->fromRequest($request), $id, $request->name(), $request->questions()));
    }

    public function destroy(Request $request, int $id, ActorResolver $actors, DeleteInterviewKit $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id);

        return response()->noContent();
    }
}
