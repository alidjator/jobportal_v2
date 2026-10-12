<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Recruitment\Application\Voice\HangUpInterviewCall;
use Src\Recruitment\Application\Voice\JoinVoiceInterview;
use Src\Recruitment\Application\Voice\ListInterviewCalls;
use Src\Recruitment\Application\Voice\PlaceInterviewCall;
use Src\Recruitment\Interface\Http\Resources\InterviewCallResource;
use Src\Recruitment\Interface\Http\Resources\VoiceJoinResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The phone interview of an application, for its panel: join the call from the browser, dial the candidate, hang up and read the call log. */
final class VoiceInterviewController extends ApiController
{
    public function join(Request $request, int $id, ActorResolver $actors, JoinVoiceInterview $join): VoiceJoinResource
    {
        return new VoiceJoinResource($join->execute($actors->fromRequest($request), $id));
    }

    public function call(Request $request, int $id, ActorResolver $actors, PlaceInterviewCall $place): JsonResponse
    {
        return (new InterviewCallResource($place->execute($actors->fromRequest($request), $id)))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function hangup(Request $request, int $id, ActorResolver $actors, HangUpInterviewCall $hangUp): InterviewCallResource
    {
        return new InterviewCallResource($hangUp->execute($actors->fromRequest($request), $id));
    }

    public function calls(Request $request, int $id, ActorResolver $actors, ListInterviewCalls $list): AnonymousResourceCollection
    {
        return InterviewCallResource::collection($list->execute($actors->fromRequest($request), $id));
    }
}
