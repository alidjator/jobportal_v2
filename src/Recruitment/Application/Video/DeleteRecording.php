<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\TeamInterviewLookup;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Recruitment\Domain\Video\RecordingStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Deletes a recording before its retention period is over. Only the interview panel and the admins of the company may; the file goes first, so a failed
 * deletion can be repeated.
 */
final readonly class DeleteRecording
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private VideoSessions $sessions,
        private RecordingRepository $recordings,
        private FileStorage $files,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-interview-participant`
     * @throws EntityNotFound
     * @throws StateConflict `recording-not-finished`
     */
    public function execute(Actor $actor, int $interviewId, int $recordingId): void
    {
        $team = $this->lookup->find($actor, $interviewId);
        $recording = $this->recordings->find($recordingId);

        if (! $recording instanceof InterviewRecording || $recording->interviewId !== $interviewId) {
            throw EntityNotFound::of('Recording', $recordingId);
        }

        if (! $team->member->isCompanyAdmin && ! $this->sessions->isOnPanel($team->interview, $team->member->userId)) {
            throw new AccessDenied('Only the interview panel and the company admins can delete a recording.', 'not-an-interview-participant');
        }

        if (in_array($recording->status, [RecordingStatus::Recording, RecordingStatus::Processing], true)) {
            throw new StateConflict('This recording is not finished yet.', 'recording-not-finished');
        }

        if ($recording->path !== null) {
            $this->files->delete($recording->path);
        }

        $this->recordings->delete($recordingId);
    }
}
