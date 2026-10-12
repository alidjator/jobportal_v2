<?php

namespace Src\Recruitment\Application\Video;

use DateInterval;
use RuntimeException;
use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\RecordingConverted;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Recruitment\Domain\Video\RecordingStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\FileStreams;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/**
 * What happens to a recording after it stopped: the converter is asked to make one file of the raw tracks, reports back, and the file moves from the shared
 * volume to the private disk, where it is kept until the retention period of the company is over. Every step is safe to run again.
 */
final readonly class RecordingPipeline
{
    public const string DIRECTORY = 'interview-recordings';

    public function __construct(
        private RecordingRepository $recordings,
        private InterviewRepository $interviews,
        private ApplicationRepository $applications,
        private RecordingConverter $converter,
        private RecordingVolume $volume,
        private FileStreams $streams,
        private FileStorage $files,
        private Settings $settings,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @throws ExternalServiceUnavailable */
    public function requestConversion(int $recordingId): void
    {
        $recording = $this->recordings->find($recordingId);

        if ($recording instanceof InterviewRecording && $recording->status === RecordingStatus::Processing && $recording->sourceFile === null) {
            $this->converter->request($recordingId, $this->volume->rawDirectory($recordingId));
        }
    }

    /** The converter finished: remembers the file and announces it. A repeated report changes nothing. */
    public function converted(int $recordingId, string $file, ?int $durationSeconds): void
    {
        $changed = $this->transactions->run(function () use ($recordingId, $file, $durationSeconds): bool {
            $recording = $this->recordings->find($recordingId, forUpdate: true);

            if (! $recording instanceof InterviewRecording || $recording->status !== RecordingStatus::Processing || $recording->sourceFile !== null) {
                return false;
            }

            $this->recordings->update($recording->converted($file, $durationSeconds));

            return true;
        });

        if ($changed) {
            $this->events->dispatch([new RecordingConverted($recordingId, $this->clock->now())]);
        }
    }

    /** @throws RuntimeException when the converted file cannot be read or written, so the queue retries */
    public function store(int $recordingId): void
    {
        $recording = $this->recordings->find($recordingId);

        if (! $recording instanceof InterviewRecording || $recording->status !== RecordingStatus::Processing || $recording->sourceFile === null) {
            return;
        }

        $interview = $this->interviews->findById($recording->interviewId);
        $application = $interview === null ? null : $this->applications->find($interview->applicationId);

        if ($interview === null || $application === null) {
            $this->fail($recordingId);

            return;
        }

        $source = $this->volume->open($recording->sourceFile) ?? throw new RuntimeException("The converted file of recording {$recordingId} is missing.");
        $path = self::DIRECTORY.'/'.$recording->interviewId.'/'.bin2hex(random_bytes(16)).'.'.pathinfo($recording->sourceFile, PATHINFO_EXTENSION);

        try {
            $this->streams->putStream($path, $source);
        } finally {
            fclose($source);
        }

        $size = $this->streams->size($path) ?? throw new RuntimeException("The recording {$recordingId} could not be stored.");
        $keepDays = $this->settings->integer(InterviewSettings::RECORDING_RETENTION_DAYS, $application->companyId);
        $this->recordings->update($recording->stored($path, $size, $interview->endsAt->add(new DateInterval('P'.$keepDays.'D'))));
        $this->volume->discard($recordingId);
    }

    /** The recording cannot be finished: it stays in the list as failed, without a file. */
    public function fail(int $recordingId): void
    {
        $recording = $this->recordings->find($recordingId, forUpdate: false);

        if (! $recording instanceof InterviewRecording || $recording->status !== RecordingStatus::Processing) {
            return;
        }

        $this->recordings->update($recording->failed());
        $this->volume->discard($recordingId);
    }
}
