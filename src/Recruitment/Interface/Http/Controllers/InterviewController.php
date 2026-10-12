<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\ListApplicationInterviews;
use Src\Recruitment\Application\ManageInterview;
use Src\Recruitment\Application\ScheduleInterview;
use Src\Recruitment\Interface\Http\Requests\ScheduleInterviewRequest;
use Src\Recruitment\Interface\Http\Resources\CompanyInterviewResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The interviews of an application, for the members who may see its job. */
final class InterviewController extends ApiController
{
    public function index(Request $request, int $id, ActorResolver $actors, ListApplicationInterviews $list): AnonymousResourceCollection
    {
        return CompanyInterviewResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    /** Schedules the interview: 201 for a new one, 200 when it replaced the active one. */
    public function store(ScheduleInterviewRequest $request, int $id, ActorResolver $actors, ScheduleInterview $schedule): JsonResponse
    {
        $result = $schedule->execute($actors->fromRequest($request), $id, $request->toInput());

        return (new CompanyInterviewResource($result['view']))->response()->setStatusCode($result['replaced'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    public function acceptProposal(Request $request, int $id, ActorResolver $actors, ManageInterview $manage): CompanyInterviewResource
    {
        return new CompanyInterviewResource($manage->acceptProposal($actors->fromRequest($request), $id));
    }

    public function cancel(Request $request, int $id, ActorResolver $actors, ManageInterview $manage): CompanyInterviewResource
    {
        return new CompanyInterviewResource($manage->cancel($actors->fromRequest($request), $id));
    }

    public function complete(Request $request, int $id, ActorResolver $actors, ManageInterview $manage): CompanyInterviewResource
    {
        return new CompanyInterviewResource($manage->complete($actors->fromRequest($request), $id));
    }
}
