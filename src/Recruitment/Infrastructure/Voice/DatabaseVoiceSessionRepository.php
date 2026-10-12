<?php

namespace Src\Recruitment\Infrastructure\Voice;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\Video\VideoJoinWindow;
use Src\Recruitment\Domain\Voice\VoiceSessionRepository;

final readonly class DatabaseVoiceSessionRepository implements VoiceSessionRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function open(int $interviewId, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_interviews')->where('id', $interviewId)->whereNull('voice_opened_at')->update(['voice_opened_at' => $this->format($at), 'voice_closed_at' => null]);
    }

    public function isOpen(int $interviewId): bool
    {
        return $this->connection->table('recruitment_interviews')->where('id', $interviewId)->whereNotNull('voice_opened_at')->whereNull('voice_closed_at')->exists();
    }

    public function close(int $interviewId, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_interviews')->where('id', $interviewId)->update(['voice_closed_at' => $this->format($at)]);
    }

    public function rememberEndpoint(int $interviewId, int $userId, string $username, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_interview_voice_endpoints')->insertOrIgnore([
            'interview_id' => $interviewId, 'user_id' => $userId, 'username' => $username, 'created_at' => $this->format($at),
        ]);
    }

    public function endpoints(int $interviewId): array
    {
        return array_map('strval', $this->connection->table('recruitment_interview_voice_endpoints')->where('interview_id', $interviewId)->orderBy('id')->pluck('username')->all());
    }

    public function forgetEndpoints(int $interviewId): void
    {
        $this->connection->table('recruitment_interview_voice_endpoints')->where('interview_id', $interviewId)->delete();
    }

    public function userOfEndpoint(int $interviewId, string $username): ?int
    {
        $id = $this->connection->table('recruitment_interview_voice_endpoints')->where('interview_id', $interviewId)->where('username', $username)->value('user_id');

        return $id === null ? null : (int) $id;
    }

    public function dueForClosing(DateTimeImmutable $now): array
    {
        $endedBefore = $now->sub(new DateInterval('PT'.VideoJoinWindow::MINUTES_AFTER.'M'));

        return array_map('intval', $this->connection->table('recruitment_interviews')
            ->whereNotNull('voice_opened_at')
            ->whereNull('voice_closed_at')
            ->where(fn ($query) => $query->where('status', '!=', InterviewStatus::Confirmed->value)->orWhere('ends_at', '<', $this->format($endedBefore)))
            ->orderBy('id')
            ->pluck('id')
            ->all());
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
