<?php

namespace Src\Recruitment\Application\Voice;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Src\Recruitment\Application\ApplicationNotificationType;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\Video\AttendanceRepository;
use Src\Recruitment\Domain\Video\InterviewAttendance;
use Src\Recruitment\Domain\Voice\CallRepository;
use Src\Recruitment\Domain\Voice\InterviewCall;
use Src\Recruitment\Domain\Voice\VoiceSessionRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Throwable;

/**
 * Turns one event of the voice server (ARI) into what the platform records: a candidate's phone ringing, answering and hanging up (the call log, the candidate's
 * missed-call notice, the time cap and the end of a recording), and the team's browsers entering and leaving the bridge (attendance). A team channel is let into a bridge only when its
 * account was handed out by this application for that interview; anything else is hung up. An event that matches nothing is ignored, and the same event twice does
 * no harm.
 */
final readonly class HandleAriEvent
{
    public function __construct(
        private CallRepository $calls,
        private VoiceSessionRepository $sessions,
        private InterviewRepository $interviews,
        private ApplicationRepository $applications,
        private AttendanceRepository $attendance,
        private VoiceGateway $gateway,
        private VoiceLimits $limits,
        private VoiceRecordingStopper $recordings,
        private InAppNotifier $notifier,
        private TransactionManager $transactions,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /** @param  array<mixed>  $event */
    public function execute(array $event): void
    {
        $channel = is_array($event['channel'] ?? null) ? $event['channel'] : [];
        $channelId = is_string($channel['id'] ?? null) ? $channel['id'] : null;

        if ($channelId === null) {
            return;
        }

        try {
            match ($event['type'] ?? null) {
                'StasisStart' => $this->started($event, $channelId, (string) ($channel['name'] ?? '')),
                'ChannelStateChange' => $this->stateChanged($channelId, (string) ($channel['state'] ?? '')),
                'ChannelDestroyed' => $this->destroyed($channelId, is_int($event['cause'] ?? null) ? $event['cause'] : null),
                default => null,
            };
        } catch (ExternalServiceUnavailable $e) {
            // The channel cannot be put where it belongs; leaving it alone would leave a caller in silence.
            $this->logger->warning('The voice server refused a request while handling an event.', ['type' => $event['type'], 'channel' => $channelId, 'exception' => $e]);
            $this->hangUpQuietly($channelId);
        }
    }

    /** @param  array<mixed>  $event */
    private function started(array $event, string $channelId, string $channelName): void
    {
        $args = is_array($event['args'] ?? null) ? array_values($event['args']) : [];

        match ($args[0] ?? null) {
            'candidate' => $this->candidateAnswered($channelId),
            'team' => $this->teamEntered($channelId, $channelName, is_string($args[1] ?? null) ? $args[1] : ''),
            default => null,
        };
    }

    private function candidateAnswered(string $channelId): void
    {
        $now = $this->clock->now();

        $placed = $this->transactions->run(function () use ($channelId, $now): ?array {
            $call = $this->calls->findByChannel($channelId, forUpdate: true);

            if (! $call instanceof InterviewCall || ! $call->status->isLive()) {
                return null;
            }

            $interview = $this->interviews->findById($call->interviewId);
            $application = $interview === null ? null : $this->applications->find($interview->applicationId);

            if ($application === null) {
                return null;
            }

            // The cap is worked out before this call counts as answered, so it does not reserve minutes against itself.
            $seconds = $this->limits->allowedSeconds($application->companyId, $now);
            $this->calls->update($call->answered($now));
            $this->attendance->joinedOnChannel($call->interviewId, new InterviewAttendance($application->userId, InterviewAttendance::CANDIDATE, $now), $channelId);

            return [$call->interviewId, $seconds];
        });

        if ($placed === null) {
            $this->hangUpQuietly($channelId);

            return;
        }

        $this->gateway->addToBridge(VoiceNames::bridge($placed[0]), $channelId);
        $this->gateway->limitDuration($channelId, max(1, $placed[1]));
    }

    private function teamEntered(string $channelId, string $channelName, string $bridgeId): void
    {
        $interviewId = VoiceNames::interviewOfBridge($bridgeId);
        $username = VoiceNames::endpointOfChannelName($channelName);
        $userId = $interviewId === null || $username === null || ! $this->sessions->isOpen($interviewId) ? null : $this->sessions->userOfEndpoint($interviewId, $username);

        if ($interviewId === null || $userId === null) {
            $this->hangUpQuietly($channelId);

            return;
        }

        $this->transactions->run(fn () => $this->attendance->joinedOnChannel($interviewId, new InterviewAttendance($userId, InterviewAttendance::COMPANY, $this->clock->now()), $channelId));
        $this->gateway->answer($channelId);
        $this->gateway->addToBridge($bridgeId, $channelId);
    }

    private function stateChanged(string $channelId, string $state): void
    {
        if (! in_array($state, ['Ring', 'Ringing'], true)) {
            return;
        }

        $this->transactions->run(function () use ($channelId): void {
            $call = $this->calls->findByChannel($channelId, forUpdate: true);

            if ($call instanceof InterviewCall && $call->status->isLive() && $call->ringing() !== $call) {
                $this->calls->update($call->ringing());
            }
        });
    }

    private function destroyed(string $channelId, ?int $cause): void
    {
        $now = $this->clock->now();

        $ended = $this->transactions->run(function () use ($channelId, $cause, $now): ?InterviewCall {
            $this->attendance->leftOnChannel($channelId, $now);
            $call = $this->calls->findByChannel($channelId, forUpdate: true);

            if (! $call instanceof InterviewCall || ! $call->status->isLive()) {
                return null;
            }

            $ended = $call->ended($now, $cause);
            $this->calls->update($ended);

            return $ended;
        });

        if (! $ended instanceof InterviewCall) {
            return;
        }

        if ($ended->status->isMissed()) {
            $this->tellCandidate($ended, $now);
        } else {
            $this->stopRecording($ended->interviewId);
        }
    }

    /** The recording ends with the call. When the voice server cannot be told now, the sweep of {@see CloseVoiceSessions} does it on its next run. */
    private function stopRecording(int $interviewId): void
    {
        try {
            $this->recordings->stopAndAnnounce($interviewId);
        } catch (ExternalServiceUnavailable $e) {
            $this->logger->warning('A call recording could not be stopped; the sweep will retry.', ['interview_id' => $interviewId, 'exception' => $e]);
        }
    }

    private function tellCandidate(InterviewCall $call, DateTimeImmutable $at): void
    {
        $interview = $this->interviews->findById($call->interviewId);
        $application = $interview === null ? null : $this->applications->find($interview->applicationId);

        if ($application === null) {
            return;
        }

        $this->notifier->send([$application->userId], ApplicationNotificationType::INTERVIEW_CALL_MISSED, [
            'application_id' => $application->id,
            'interview_id' => $call->interviewId,
            'job_id' => $application->jobId,
            'called_at' => $at->format(DATE_ATOM),
        ]);
    }

    private function hangUpQuietly(string $channelId): void
    {
        try {
            $this->gateway->hangup($channelId);
        } catch (Throwable $e) {
            $this->logger->warning('A channel could not be hung up.', ['channel' => $channelId, 'exception' => $e]);
        }
    }
}
