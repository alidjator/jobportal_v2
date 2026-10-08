<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Recruitment\Application\GetMyInterview;
use Src\Recruitment\Application\RespondToInterview;
use Src\Recruitment\Interface\Http\Requests\ProposeInterviewTimeRequest;
use Src\Recruitment\Interface\Http\Resources\InterviewResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The active interview of the job seeker's own application: read it, confirm it, decline it or propose another time. */
final class MyInterviewController extends ApiController
{
    /** The active interview, or `{"data": null}` when the application has none. */
    public function show(Request $request, int $id, ActorResolver $actors, GetMyInterview $get): InterviewResource|array
    {
        $view = $get->execute($actors->fromRequest($request), $id);

        return $view === null ? ['data' => null] : new InterviewResource($view);
    }

    public function confirm(Request $request, int $id, ActorResolver $actors, RespondToInterview $respond): InterviewResource
    {
        return new InterviewResource($respond->confirm($actors->fromRequest($request), $id));
    }

    public function decline(Request $request, int $id, ActorResolver $actors, RespondToInterview $respond): InterviewResource
    {
        return new InterviewResource($respond->decline($actors->fromRequest($request), $id));
    }

    public function propose(ProposeInterviewTimeRequest $request, int $id, ActorResolver $actors, RespondToInterview $respond): InterviewResource
    {
        return new InterviewResource($respond->propose($actors->fromRequest($request), $id, $request->startsAt()));
    }
}
