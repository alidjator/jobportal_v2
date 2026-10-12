<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Recruitment\Application\Video\JoinVideoInterview;
use Src\Recruitment\Application\Video\ViewVideoLobby;
use Src\Recruitment\Interface\Http\Resources\VideoJoinResource;
use Src\Recruitment\Interface\Http\Resources\VideoLobbyResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The video interview of the job seeker's own application: ask to enter (the lobby, then the credentials once admitted) and poll the lobby. */
final class MyVideoInterviewController extends ApiController
{
    public function join(Request $request, int $id, ActorResolver $actors, JoinVideoInterview $join): VideoJoinResource
    {
        return new VideoJoinResource($join->asApplicant($actors->fromRequest($request), $id));
    }

    public function lobby(Request $request, int $id, ActorResolver $actors, ViewVideoLobby $lobby): VideoLobbyResource
    {
        return new VideoLobbyResource($lobby->asApplicant($actors->fromRequest($request), $id));
    }
}
