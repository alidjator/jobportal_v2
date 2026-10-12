<?php

namespace Src\Assessment\Application\Video;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Application\Attempts\AttemptContext;
use Src\Assessment\Application\Attempts\AttemptGate;
use Src\Assessment\Domain\VideoContent;
use Src\Assessment\Domain\VideoTake;
use Src\Assessment\Domain\VideoTakeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The browser sends the recording in chunks, in order. The first chunk shows what the file is (WebM or MP4, read from its bytes); the total size is held
 * to the setting. Sending the chunk the server already has again is a no-op, so a retry after a lost response is safe.
 */
final readonly class UploadVideoChunk
{
    public const int MAX_CHUNK_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private AttemptGate $gate,
        private VideoTakes $videos,
        private VideoTakeRepository $takes,
        private Settings $settings,
        private FileStorage $files,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws StateConflict `attempt-not-started`, `attempt-closed`, `question-locked`, `video-take-closed`, `upload-deadline-passed` or `chunk-out-of-order`
     */
    public function execute(Actor $actor, int $invitationId, int $questionId, int $takeId, int $index, string $contents, ?string $ip): VideoTake
    {
        return $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($questionId, $takeId, $index, $contents): VideoTake {
            $this->videos->question($context, $questionId);
            $take = $this->videos->take($context, $questionId, $takeId);

            if (! $take->isRecording()) {
                throw new StateConflict('This recording is closed.', 'video-take-closed');
            }

            if ($take->isPastDeadline($context->now)) {
                throw new StateConflict('The time to send this recording has passed.', 'upload-deadline-passed');
            }

            if ($index < $take->chunkCount) {
                return $take;
            }

            if ($index > $take->chunkCount) {
                throw new StateConflict('Send the chunks in order.', 'chunk-out-of-order', extensions: ['expected_index' => $take->chunkCount]);
            }

            $size = strlen($contents);

            if ($size === 0) {
                throw $this->invalid('The chunk is empty.');
            }

            if ($size > self::MAX_CHUNK_BYTES) {
                throw new BusinessRuleViolation('A chunk is larger than the allowed 5 MB.', 'file-too-large', extensions: ['max_size_mb' => 5]);
            }

            $content = null;

            if ($index === 0) {
                $content = VideoContent::detect(substr($contents, 0, 16)) ?? throw $this->invalid('The recording must be a WebM or MP4 video.');
            }

            $maxMb = $this->settings->integer(AssessmentSettings::MAX_VIDEO_MEGABYTES);

            if ($take->sizeBytes + $size > $maxMb * 1024 * 1024) {
                throw new BusinessRuleViolation("The recording is larger than the allowed {$maxMb} MB.", 'video-too-large', extensions: ['max_size_mb' => $maxMb]);
            }

            $this->files->put(VideoTakes::chunkPath($takeId, $index), $contents);

            $next = $take->withChunk($size, $content['mime'] ?? (string) $take->mimeType);
            $this->takes->update($next);

            return $next;
        });
    }

    private function invalid(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-video', extensions: ['errors' => ['file' => [$message]]]);
    }
}
