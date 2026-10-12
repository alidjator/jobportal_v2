<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\TeamInterviewLookup;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Recruitment\Domain\Video\RecordingStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStreams;
use Src\Shared\Application\StreamedFile;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Opens a ready recording for the members who may see the application. A recording of another interview is a plain 404. */
final readonly class OpenRecording
{
    public function __construct(private TeamInterviewLookup $lookup, private RecordingRepository $recordings, private FileStreams $streams) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `recording-not-ready`
     */
    public function execute(Actor $actor, int $interviewId, int $recordingId): StreamedFile
    {
        $this->lookup->find($actor, $interviewId);
        $recording = $this->recordings->find($recordingId);

        if (! $recording instanceof InterviewRecording || $recording->interviewId !== $interviewId) {
            throw EntityNotFound::of('Recording', $recordingId);
        }

        if ($recording->status !== RecordingStatus::Ready || $recording->path === null) {
            throw new StateConflict('This recording has no file.', 'recording-not-ready');
        }

        $stream = $this->streams->readStream($recording->path) ?? throw EntityNotFound::of('Recording', $recordingId);
        $extension = pathinfo($recording->path, PATHINFO_EXTENSION);

        return new StreamedFile($stream, (int) $recording->sizeBytes, $extension === 'mp4' ? 'video/mp4' : 'video/webm', "interview-{$interviewId}-recording-{$recordingId}.{$extension}");
    }
}
