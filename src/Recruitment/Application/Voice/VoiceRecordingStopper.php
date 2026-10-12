<?php

namespace Src\Recruitment\Application\Voice;

use Src\Recruitment\Domain\Events\VoiceRecordingStopped;
use Src\Recruitment\Domain\Video\InterviewRecording;
use Src\Recruitment\Domain\Video\RecordingMedia;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/**
 * Stops the running recording of a phone call: for a member who pressed stop, for the call ending and for the sweep that finds a recording with no call left.
 * The file is fetched afterwards by a queued job, which is started only once the stop is committed.
 */
final readonly class VoiceRecordingStopper
{
    public function __construct(
        private RecordingRepository $recordings,
        private VoiceGateway $gateway,
        private EventDispatcher $events,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * Ends the voice server's recording and marks it as processing; null when none was running. Runs inside the caller's transaction, which then calls {@see self::announce()}.
     *
     * @throws ExternalServiceUnavailable
     */
    public function stop(int $interviewId): ?InterviewRecording
    {
        $running = $this->recordings->active($interviewId, forUpdate: true, media: RecordingMedia::Audio);

        if (! $running instanceof InterviewRecording) {
            return null;
        }

        $this->gateway->stopRecording(VoiceNames::recording((int) $running->id));

        $stopped = $running->stop($this->clock->now());
        $this->recordings->update($stopped);

        return $stopped;
    }

    /** Starts fetching the file. */
    public function announce(InterviewRecording $stopped): void
    {
        $this->events->dispatch([new VoiceRecordingStopped((int) $stopped->id, $this->clock->now())]);
    }

    /**
     * {@see self::stop()} in a transaction of its own, then announced.
     *
     * @throws ExternalServiceUnavailable
     */
    public function stopAndAnnounce(int $interviewId): ?InterviewRecording
    {
        $stopped = $this->transactions->run(fn (): ?InterviewRecording => $this->stop($interviewId));

        if ($stopped instanceof InterviewRecording) {
            $this->announce($stopped);
        }

        return $stopped;
    }
}
