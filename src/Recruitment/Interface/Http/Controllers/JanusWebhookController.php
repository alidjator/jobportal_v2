<?php

namespace Src\Recruitment\Interface\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Recruitment\Application\Video\CompleteRecordingConversion;
use Src\Recruitment\Application\Video\RecordJanusEvents;
use Src\Recruitment\Interface\Http\Requests\RecordingResultRequest;
use Src\Shared\Interface\Http\ApiController;

/** The internal webhooks of the video server and its converter (guarded by a shared secret, reachable from the Docker network only). */
final class JanusWebhookController extends ApiController
{
    /** The event handler posts one event or a list of them. */
    public function events(Request $request, RecordJanusEvents $record): Response
    {
        $payload = $request->json()->all();
        $record->execute(array_is_list($payload) ? $payload : [$payload]);

        return response()->noContent();
    }

    public function recordings(RecordingResultRequest $request, CompleteRecordingConversion $complete): Response
    {
        if ($request->ready()) {
            $complete->succeeded($request->recordingId(), $request->convertedFile(), $request->durationSeconds());
        } else {
            $complete->failed($request->recordingId());
        }

        return response()->noContent();
    }
}
