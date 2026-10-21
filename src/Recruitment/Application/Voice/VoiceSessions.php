<?php

namespace Src\Recruitment\Application\Voice;

use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Application\Video\IceServerProvider;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\Video\VideoJoinWindow;
use Src\Recruitment\Domain\Voice\VoiceSessionRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * What every voice action needs: the interview of the application checked to be callable now, who is on its panel, and the credentials of a join. The bridge is
 * opened by the first join inside the window. Everything here runs inside the caller's transaction with the application row locked.
 */
final readonly class VoiceSessions
{
    public function __construct(
        private InterviewRepository $interviews,
        private InterviewPanel $panel,
        private VoiceSessionRepository $sessions,
        private VoiceGateway $gateway,
        private IceServerProvider $iceServers,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * The active interview of the application when the team may be on the phone for it now: a confirmed phone interview, or a confirmed video interview the job
     * seeker gave a fallback number for, inside the join window, while phone interviews are switched on.
     *
     * @throws StateConflict `no-active-interview`, `not-a-voice-interview`, `interview-not-confirmed`, `voice-disabled`, `voice-window-closed` or `no-phone-number`
     */
    public function callable(Application $application): Interview
    {
        $interview = $this->interviews->active($application->id ?? 0, forUpdate: true) ?? throw new StateConflict('This application has no active interview.', 'no-active-interview');

        if (! in_array($interview->format, [InterviewFormat::Voice, InterviewFormat::Video], true)) {
            throw new StateConflict('This interview is not held by phone.', 'not-a-voice-interview');
        }

        if ($interview->status !== InterviewStatus::Confirmed) {
            throw new StateConflict('Only a confirmed interview can be called.', 'interview-not-confirmed');
        }

        if (! $this->settings->boolean(InterviewSettings::VOICE_ENABLED, $application->companyId)) {
            throw new StateConflict('Phone interviews are switched off.', 'voice-disabled');
        }

        if ($interview->candidatePhone === null) {
            throw new StateConflict($interview->format === InterviewFormat::Voice ? 'The candidate has not given a number to call.' : 'The candidate gave no fallback number for this interview.', 'no-phone-number');
        }

        if (! VideoJoinWindow::isOpen($interview, $this->clock->now())) {
            throw new StateConflict(
                'Calls are possible from :before minutes before the interview until :after minutes after it ends.',
                'voice-window-closed',
                parameters: ['before' => VideoJoinWindow::MINUTES_BEFORE, 'after' => VideoJoinWindow::MINUTES_AFTER],
            );
        }

        return $interview;
    }

    /** The main interviewer or a member of the panel. */
    public function isOnPanel(Interview $interview, int $userId): bool
    {
        return $interview->interviewerId === $userId || in_array($userId, $this->panel->forInterviews([(int) $interview->id])[(int) $interview->id] ?? [], true);
    }

    /**
     * Gives the user a browser account for the interview, opening the bridge first when this is the first join. A join again replaces the password.
     *
     * @throws ExternalServiceUnavailable
     */
    public function credentials(Interview $interview, int $userId): VoiceCredentials
    {
        $interviewId = (int) $interview->id;
        $bridgeId = VoiceNames::bridge($interviewId);
        $opened = false;

        try {
            if (! $this->sessions->isOpen($interviewId)) {
                $this->gateway->openBridge($bridgeId);
                $this->sessions->open($interviewId, $this->clock->now());
                $opened = true;
            }

            $username = VoiceNames::endpoint($interviewId, $userId);
            $password = bin2hex(random_bytes(12));
            $this->gateway->provisionEndpoint($username, $password);
            $this->sessions->rememberEndpoint($interviewId, $userId, $username, $this->clock->now());
        } catch (Throwable $failure) {
            // The transaction rolls the opening back, so the bridge must not stay on the voice server.
            if ($opened) {
                $this->discard($bridgeId);
            }

            throw $failure;
        }

        $expiresAt = VideoJoinWindow::closesAt($interview);

        return new VoiceCredentials($username, $password, $this->gateway->sipDomain(), $this->gateway->websocketUrl(), $bridgeId, $this->iceServers->forUser($userId, $expiresAt), $expiresAt);
    }

    private function discard(string $bridgeId): void
    {
        try {
            $this->gateway->closeBridge($bridgeId);
        } catch (ExternalServiceUnavailable) {
            // The original failure is the one to report.
        }
    }
}
