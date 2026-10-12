<?php

namespace Src\Recruitment\Application\Video;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\Video\AttendanceRepository;
use Src\Recruitment\Domain\Video\InterviewAttendance;
use Src\Recruitment\Domain\Video\VideoRoomRepository;
use Src\Shared\Application\Clock;

/**
 * Turns the events the video server posts into attendance: who joined the room of an interview and when they left. Only a `joined` event whose display name
 * was signed by this application counts, so nobody can add themselves to the list. Anything else in the stream is ignored.
 */
final readonly class RecordJanusEvents
{
    private const string PLUGIN = 'janus.plugin.videoroom';

    public function __construct(
        private VideoRoomRepository $rooms,
        private InterviewRepository $interviews,
        private ApplicationRepository $applications,
        private AttendanceRepository $attendance,
        private JoinTokenFactory $tokens,
        private Clock $clock,
    ) {}

    /**
     * @param  list<mixed>  $events  as posted by the event handler
     * @return int the number of attendance changes recorded
     */
    public function execute(array $events): int
    {
        $recorded = 0;

        foreach ($events as $event) {
            if (is_array($event) && $this->record($event)) {
                $recorded++;
            }
        }

        return $recorded;
    }

    /** @param  array<mixed>  $event */
    private function record(array $event): bool
    {
        $body = $event['event'] ?? null;
        $data = is_array($body) ? ($body['data'] ?? null) : null;
        $sessionId = $event['session_id'] ?? null;

        if (! is_array($body) || ! is_array($data) || ($body['plugin'] ?? null) !== self::PLUGIN || ! is_int($sessionId) || ! is_int($data['room'] ?? null)) {
            return false;
        }

        $interviewId = $this->rooms->interviewOfRoom($data['room']);

        if ($interviewId === null) {
            return false;
        }

        $at = $this->time($event['timestamp'] ?? null);

        return match ($data['event'] ?? null) {
            'joined' => $this->joined($interviewId, $data['room'], $sessionId, $data['display'] ?? null, $at),
            'leaving' => $this->left($interviewId, $sessionId, $at),
            default => false,
        };
    }

    private function joined(int $interviewId, int $roomId, int $sessionId, mixed $display, DateTimeImmutable $at): bool
    {
        $userId = is_string($display) ? $this->tokens->userOfDisplay($display, $interviewId, $roomId) : null;
        $interview = $userId === null ? null : $this->interviews->findById($interviewId);
        $application = $interview === null ? null : $this->applications->find($interview->applicationId);

        if ($userId === null || $application === null) {
            return false;
        }

        $role = $application->userId === $userId ? InterviewAttendance::CANDIDATE : InterviewAttendance::COMPANY;
        $this->attendance->joined($interviewId, new InterviewAttendance($userId, $role, $at, null, $sessionId));

        return true;
    }

    private function left(int $interviewId, int $sessionId, DateTimeImmutable $at): bool
    {
        $this->attendance->left($interviewId, $sessionId, $at);

        return true;
    }

    /** Janus stamps events in microseconds since the epoch. */
    private function time(mixed $microseconds): DateTimeImmutable
    {
        return is_int($microseconds) && $microseconds > 0
            ? (new DateTimeImmutable('@'.intdiv($microseconds, 1_000_000)))->setTimezone(new DateTimeZone('UTC'))
            : $this->clock->now();
    }
}
