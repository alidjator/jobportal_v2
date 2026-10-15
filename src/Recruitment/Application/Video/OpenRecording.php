<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Application\TeamInterviewLookup;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Recruitment\Domain\Video\RecordingStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\FileStreams;
use Src\Shared\Application\StreamedFile;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Opens a ready recording for the members who may see the application. A recording of another interview is a plain 404. The download is audited (`interview.recording_downloaded`) for the company activity log, once per
 * playback: a player asks for the file in many ranges, so only the request for the start (`$firstRange`) is recorded. */
final readonly class OpenRecording
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private RecordingRepository $recordings,
        private FileStreams $streams,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `recording-not-ready`
     */
    public function execute(Actor $actor, int $interviewId, int $recordingId, bool $firstRange = true, ?string $ipAddress = null, ?string $userAgent = null): StreamedFile
    {
        $this->lookup->find($actor, $interviewId, permission: CompanyPermission::InterviewsManage);
        $recording = $this->recordings->find($recordingId);

        if (! $recording instanceof InterviewRecording || $recording->interviewId !== $interviewId) {
            throw EntityNotFound::of('Recording', $recordingId);
        }

        if ($recording->status !== RecordingStatus::Ready || $recording->path === null) {
            throw new StateConflict('This recording has no file.', 'recording-not-ready');
        }

        $stream = $this->streams->readStream($recording->path) ?? throw EntityNotFound::of('Recording', $recordingId);
        if ($firstRange) {
            $this->audit->record(new AuditEntry(
                AuditActorType::User, $actor->id, AuditAction::INTERVIEW_RECORDING_DOWNLOADED, 'interview', $interviewId, ['recording_id' => $recordingId], $ipAddress, $userAgent, $this->clock->now(),
            ));
        }

        $extension = pathinfo($recording->path, PATHINFO_EXTENSION);
        $mime = match ($extension) {
            'mp4' => 'video/mp4',
            'wav' => 'audio/wav',
            default => 'video/webm',
        };

        return new StreamedFile($stream, (int) $recording->sizeBytes, $mime, "interview-{$interviewId}-recording-{$recordingId}.{$extension}");
    }
}
