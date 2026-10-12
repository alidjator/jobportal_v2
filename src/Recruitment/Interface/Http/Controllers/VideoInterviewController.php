<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Src\Recruitment\Application\Video\AdmitToVideoInterview;
use Src\Recruitment\Application\Video\JoinVideoInterview;
use Src\Recruitment\Application\Video\ViewVideoLobby;
use Src\Recruitment\Interface\Http\Resources\VideoJoinResource;
use Src\Recruitment\Interface\Http\Resources\VideoLobbyResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The video interview of an application, for its panel: join the room, see the lobby and admit the candidate. */
final class VideoInterviewController extends ApiController
{
    public function join(Request $request, int $id, ActorResolver $actors, JoinVideoInterview $join): VideoJoinResource
    {
        return new VideoJoinResource($join->asTeam($actors->fromRequest($request), $id));
    }

    public function lobby(Request $request, int $id, ActorResolver $actors, ViewVideoLobby $lobby): VideoLobbyResource
    {
        return new VideoLobbyResource($lobby->asTeam($actors->fromRequest($request), $id));
    }

    public function admit(Request $request, int $id, ActorResolver $actors, AdmitToVideoInterview $admit): VideoLobbyResource
    {
        return new VideoLobbyResource($admit->execute($actors->fromRequest($request), $id));
    }
}
