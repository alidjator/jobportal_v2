<?php

namespace Src\Recruitment\Application\Voice;

use Src\Recruitment\Domain\Video\RecordingMedia;
use Src\Recruitment\Domain\Video\RecordingRepository;
use Src\Recruitment\Domain\Voice\CallRepository;
use Src\Recruitment\Domain\Voice\VoiceSessionRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/**
 * Closes the voice bridges of interviews that were cancelled, declined, replaced or ran out of their join window: a call still in progress is hung up, the
 * browser accounts are removed and the bridge is closed. A session that cannot be closed now (the voice server is down) stays open and is tried again on the
 * next run. A recording whose call is over is stopped too. A call that was never closed by the voice server's own events (a lost event) is closed here once it is longer than any call can last.
 */
final readonly class CloseVoiceSessions
{
    /** Longer than the longest call an admin can allow (240 minutes) plus the time to dial. */
    private const int LOST_AFTER_MINUTES = 250;

    public function __construct(
        private VoiceSessionRepository $sessions,
        private CallRepository $calls,
        private RecordingRepository $recordings,
        private VoiceRecordingStopper $recordingStopper,
        private VoiceGateway $gateway,
        private Clock $clock,
    ) {}

    /** @return int the number of sessions closed */
    public function execute(): int
    {
        $now = $this->clock->now();
        $closed = 0;

        $this->stopRecordingsWithoutCall();

        foreach ($this->sessions->dueForClosing($now) as $interviewId) {
            try {
                $live = $this->calls->liveOf($interviewId);

                if ($live !== null) {
                    $this->gateway->hangup($live->channelId);
                }

                foreach ($this->sessions->endpoints($interviewId) as $username) {
                    $this->gateway->removeEndpoint($username);
                }

                $this->gateway->closeBridge(VoiceNames::bridge($interviewId));
            } catch (ExternalServiceUnavailable) {
                continue;
            }

            $this->sessions->forgetEndpoints($interviewId);
            $this->sessions->close($interviewId, $now);
            $closed++;
        }

        foreach ($this->calls->liveBefore($now->modify('-'.self::LOST_AFTER_MINUTES.' minutes')) as $call) {
            $this->calls->update($call->abandoned($now));
        }

        return $closed;
    }

    /** A recording lasts as long as its call; one still running with no call left missed the hangup event (or the voice server was down for it). */
    private function stopRecordingsWithoutCall(): void
    {
        foreach ($this->recordings->running(RecordingMedia::Audio) as $recording) {
            if ($this->calls->liveOf($recording->interviewId) !== null) {
                continue;
            }

            try {
                $this->recordingStopper->stopAndAnnounce($recording->interviewId);
            } catch (ExternalServiceUnavailable) {
                continue;
            }
        }
    }
}
