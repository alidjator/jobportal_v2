<?php

namespace Src\Recruitment\Application\Voice;

use DateInterval;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Application\Video\RecordingPipeline;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingMedia;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Recruitment\Domain\Video\RecordingStatus;
use Src\Shared\Application\FileStreams;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/**
 * What happens to the recording of a phone call after it stopped: the file is fetched from the voice server, written to the private disk (kept until the
 * retention period of the company is over) and removed from the voice server. Every step is safe to run again.
 */
final readonly class VoiceRecordingPipeline
{
    public function __construct(
        private RecordingRepository $recordings,
        private InterviewRepository $interviews,
        private ApplicationRepository $applications,
        private VoiceGateway $gateway,
        private FileStreams $streams,
        private Settings $settings,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws RuntimeException when the voice server has no file yet or it cannot be written, so the queue retries
     * @throws ExternalServiceUnavailable
     */
    public function store(int $recordingId): void
    {
        $recording = $this->recordings->find($recordingId);

        if (! $recording instanceof InterviewRecording || $recording->media !== RecordingMedia::Audio || $recording->status !== RecordingStatus::Processing) {
            return;
        }

        $interview = $this->interviews->findById($recording->interviewId);
        $application = $interview === null ? null : $this->applications->find($interview->applicationId);

        if ($interview === null || $application === null) {
            $this->fail($recordingId);

            return;
        }

        $name = VoiceNames::recording($recordingId);
        // The voice server finishes writing the file a moment after the stop, so a missing file is retried.
        $source = $this->gateway->fetchRecording($name) ?? throw new RuntimeException("The recording {$recordingId} is not on the voice server yet.");
        $path = RecordingPipeline::DIRECTORY.'/'.$recording->interviewId.'/'.bin2hex(random_bytes(16)).'.wav';

        try {
            $this->streams->putStream($path, $source);
        } finally {
            fclose($source);
        }

        $size = $this->streams->size($path) ?? throw new RuntimeException("The recording {$recordingId} could not be stored.");
        $keepDays = $this->settings->integer(InterviewSettings::RECORDING_RETENTION_DAYS, $application->companyId);
        $this->recordings->update($recording->stored($path, $size, $interview->endsAt->add(new DateInterval('P'.$keepDays.'D'))));
        $this->discard($name);
    }

    /** The recording cannot be finished: it stays in the list as failed, without a file. */
    public function fail(int $recordingId): void
    {
        $recording = $this->recordings->find($recordingId);

        if (! $recording instanceof InterviewRecording || $recording->media !== RecordingMedia::Audio || $recording->status !== RecordingStatus::Processing) {
            return;
        }

        $this->recordings->update($recording->failed());
        $this->discard(VoiceNames::recording($recordingId));
    }

    /** What is left on the voice server is cleaned there as well (`deploy/asterisk/`), so a failure here is only logged. */
    private function discard(string $name): void
    {
        try {
            $this->gateway->deleteRecording($name);
        } catch (ExternalServiceUnavailable $e) {
            $this->logger->warning('A recording could not be removed from the voice server.', ['recording' => $name, 'exception' => $e]);
        }
    }
}
