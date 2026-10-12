<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Recruitment\Application\Video\DeleteRecording;
use Src\Recruitment\Application\Video\ListAttendance;
use Src\Recruitment\Application\Video\ListInterviewRecordings;
use Src\Recruitment\Application\Video\OpenRecording;
use Src\Recruitment\Application\Video\StartRecording;
use Src\Recruitment\Application\Video\StopRecording;
use Src\Recruitment\Interface\Http\Resources\AttendanceResource;
use Src\Recruitment\Interface\Http\Resources\RecordingResource;
use Src\Shared\Interface\Http\ActorResolver;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\FileStreamResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** Recording of a video interview and what it left behind (the recordings and who attended), for the members who may see the application. */
final class InterviewRecordingController extends ApiController
{
    public function start(Request $request, int $id, ActorResolver $actors, StartRecording $start): JsonResponse
    {
        return (new RecordingResource($start->execute($actors->fromRequest($request), $id)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function stop(Request $request, int $id, ActorResolver $actors, StopRecording $stop): RecordingResource
    {
        return new RecordingResource($stop->execute($actors->fromRequest($request), $id));
    }

    public function index(Request $request, int $id, ActorResolver $actors, ListInterviewRecordings $list): AnonymousResourceCollection
    {
        return RecordingResource::collection($list->execute($actors->fromRequest($request), $id));
    }

    public function file(Request $request, int $id, int $recordingId, ActorResolver $actors, OpenRecording $open): SymfonyResponse
    {
        return FileStreamResponse::make($request, $open->execute($actors->fromRequest($request), $id, $recordingId));
    }

    public function destroy(Request $request, int $id, int $recordingId, ActorResolver $actors, DeleteRecording $delete): Response
    {
        $delete->execute($actors->fromRequest($request), $id, $recordingId);

        return response()->noContent();
    }

    public function attendance(Request $request, int $id, ActorResolver $actors, ListAttendance $list): AnonymousResourceCollection
    {
        return AttendanceResource::collection($list->execute($actors->fromRequest($request), $id));
    }
}
