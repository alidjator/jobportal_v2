<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\VideoTake;
use Src\Assessment\Domain\VideoTakeRepository;
use Src\Assessment\Domain\VideoTakeStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStreams;
use Src\Shared\Application\StreamedFile;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Opens the video a candidate recorded for a question, for the company members who may see the application. It is streamed, never offered as a download. */
final readonly class OpenCandidateVideo
{
    public function __construct(
        private VisibleInvitations $visible,
        private AttemptRepository $attempts,
        private VideoTakeRepository $takes,
        private FileStreams $streams,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `video-expired`
     */
    public function execute(Actor $actor, int $invitationId, int $questionId): StreamedFile
    {
        $invitation = $this->visible->findForResults($actor, $invitationId);
        $attempt = $this->attempts->findByInvitation((int) $invitation->id);

        if ($attempt === null || ! $attempt->isSubmitted()) {
            throw EntityNotFound::of('Video', $questionId);
        }

        $answer = null;

        foreach ($this->takes->ofQuestion((int) $attempt->id, $questionId) as $take) {
            $answer = in_array($take->status, [VideoTakeStatus::Complete, VideoTakeStatus::Purged], true) ? $take : $answer;
        }

        if (! $answer instanceof VideoTake) {
            throw EntityNotFound::of('Video', $questionId);
        }

        if ($answer->status === VideoTakeStatus::Purged || $answer->path === null) {
            throw new StateConflict('The retention period of this video is over and the file was deleted.', 'video-expired');
        }

        $stream = $this->streams->readStream($answer->path) ?? throw EntityNotFound::of('Video', $questionId);
        $extension = pathinfo($answer->path, PATHINFO_EXTENSION);

        return new StreamedFile($stream, $answer->sizeBytes, (string) $answer->mimeType, "answer-{$invitationId}-{$questionId}.{$extension}");
    }
}
