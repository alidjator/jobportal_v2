<?php

namespace Src\Recruitment\Infrastructure\Video;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\Video\AttendanceRepository;
use Src\Recruitment\Domain\Video\InterviewAttendance;
use stdClass;

final readonly class DatabaseAttendanceRepository implements AttendanceRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string TABLE = 'recruitment_interview_attendances';

    public function __construct(private ConnectionInterface $connection) {}

    public function joined(int $interviewId, InterviewAttendance $attendance): void
    {
        // The video server may report the same join twice; one open row per session is enough.
        if ($attendance->sessionId !== null && $this->connection->table(self::TABLE)->where('interview_id', $interviewId)->where('janus_session_id', $attendance->sessionId)->whereNull('left_at')->exists()) {
            return;
        }

        $this->connection->table(self::TABLE)->insert([
            'interview_id' => $interviewId,
            'user_id' => $attendance->userId,
            'role' => $attendance->role,
            'joined_at' => $this->format($attendance->joinedAt),
            'janus_session_id' => $attendance->sessionId,
        ]);
    }

    public function left(int $interviewId, int $sessionId, DateTimeImmutable $at): void
    {
        $this->connection->table(self::TABLE)
            ->where('interview_id', $interviewId)
            ->where('janus_session_id', $sessionId)
            ->whereNull('left_at')
            ->update(['left_at' => $this->format($at)]);
    }

    public function joinedOnChannel(int $interviewId, InterviewAttendance $attendance, string $channelId): void
    {
        if ($this->connection->table(self::TABLE)->where('channel_id', $channelId)->whereNull('left_at')->exists()) {
            return;
        }

        $this->connection->table(self::TABLE)->insert([
            'interview_id' => $interviewId,
            'user_id' => $attendance->userId,
            'role' => $attendance->role,
            'joined_at' => $this->format($attendance->joinedAt),
            'channel_id' => $channelId,
        ]);
    }

    public function leftOnChannel(string $channelId, DateTimeImmutable $at): void
    {
        $this->connection->table(self::TABLE)->where('channel_id', $channelId)->whereNull('left_at')->update(['left_at' => $this->format($at)]);
    }

    public function forInterview(int $interviewId): array
    {
        return $this->connection->table(self::TABLE)
            ->where('interview_id', $interviewId)
            ->orderBy('joined_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $row): InterviewAttendance => new InterviewAttendance(
                (int) $row->user_id,
                (string) $row->role,
                new DateTimeImmutable((string) $row->joined_at, new DateTimeZone('UTC')),
                $row->left_at === null ? null : new DateTimeImmutable((string) $row->left_at, new DateTimeZone('UTC')),
                $row->janus_session_id === null ? null : (int) $row->janus_session_id,
            ))
            ->all();
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
