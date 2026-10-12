<?php

namespace Src\Assessment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Assessment\Application\Video\CompleteVideoTake;
use Src\Assessment\Application\Video\GetVideoState;
use Src\Assessment\Application\Video\StartVideoTake;
use Src\Assessment\Application\Video\UploadVideoChunk;
use Src\Assessment\Interface\Http\Resources\VideoStateResource;
use Src\Assessment\Interface\Http\Resources\VideoTakeResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;

/** The candidate recording the answer to a video question: start a take, send its chunks in order, complete it. */
final class VideoTakeController extends ApiController
{
    public function show(Request $request, int $id, int $questionId, ActorResolver $actors, GetVideoState $get): VideoStateResource
    {
        return new VideoStateResource($get->execute($actors->fromRequest($request), $id, $questionId, $request->ip()));
    }

    public function start(Request $request, int $id, int $questionId, ActorResolver $actors, StartVideoTake $start): JsonResponse
    {
        return (new VideoTakeResource($start->execute($actors->fromRequest($request), $id, $questionId, $request->ip())))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /** The chunk is the raw request body (`application/octet-stream`). */
    public function chunk(Request $request, int $id, int $questionId, int $takeId, int $index, ActorResolver $actors, UploadVideoChunk $upload): VideoTakeResource
    {
        return new VideoTakeResource($upload->execute($actors->fromRequest($request), $id, $questionId, $takeId, $index, $request->getContent(), $request->ip()));
    }

    public function complete(Request $request, int $id, int $questionId, int $takeId, ActorResolver $actors, CompleteVideoTake $complete): VideoTakeResource
    {
        return new VideoTakeResource($complete->execute($actors->fromRequest($request), $id, $questionId, $takeId, $request->ip()));
    }
}
