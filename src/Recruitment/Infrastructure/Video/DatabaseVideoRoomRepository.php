<?php

namespace Src\Recruitment\Infrastructure\Video;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\Video\VideoJoinWindow;
use Src\Recruitment\Domain\Video\VideoRoom;
use Src\Recruitment\Domain\Video\VideoRoomRepository;

final readonly class DatabaseVideoRoomRepository implements VideoRoomRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    /** The key of the advisory lock that serialises the capacity check. */
    private const string CAPACITY_LOCK = 'recruitment-video-rooms';

    public function __construct(private ConnectionInterface $connection, private Encrypter $encrypter) {}

    public function find(int $interviewId): ?VideoRoom
    {
        $row = $this->connection->table('recruitment_interviews')->where('id', $interviewId)->first();

        if ($row === null) {
            return null;
        }

        return new VideoRoom(
            $interviewId,
            $row->video_room_id === null ? null : (int) $row->video_room_id,
            $row->video_room_pin === null ? null : (string) $this->encrypter->decryptString((string) $row->video_room_pin),
            $row->video_room_secret === null ? null : (string) $this->encrypter->decryptString((string) $row->video_room_secret),
            $this->time($row->video_room_closed_at),
            $this->time($row->candidate_waiting_at),
            $this->time($row->admitted_at),
            $row->admitted_by === null ? null : (int) $row->admitted_by,
        );
    }

    public function interviewOfRoom(int $roomId): ?int
    {
        $id = $this->connection->table('recruitment_interviews')->where('video_room_id', $roomId)->whereNull('video_room_closed_at')->value('id');

        return $id === null ? null : (int) $id;
    }

    public function open(int $interviewId, int $roomId, string $pin, string $secret): void
    {
        $this->connection->table('recruitment_interviews')->where('id', $interviewId)->update([
            'video_room_id' => $roomId,
            'video_room_pin' => $this->encrypter->encryptString($pin),
            'video_room_secret' => $this->encrypter->encryptString($secret),
            'video_room_closed_at' => null,
        ]);
    }

    public function markWaiting(int $interviewId, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_interviews')->where('id', $interviewId)->update(['candidate_waiting_at' => $this->format($at)]);
    }

    public function admit(int $interviewId, int $byUserId, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_interviews')->where('id', $interviewId)->update(['admitted_at' => $this->format($at), 'admitted_by' => $byUserId]);
    }

    public function close(int $interviewId, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_interviews')->where('id', $interviewId)->update(['video_room_closed_at' => $this->format($at)]);
    }

    public function openRoomCount(): int
    {
        return $this->connection->table('recruitment_interviews')->whereNotNull('video_room_id')->whereNull('video_room_closed_at')->count();
    }

    public function lockCapacity(): void
    {
        $this->connection->selectOne('select pg_advisory_xact_lock(hashtext(?))', [self::CAPACITY_LOCK]);
    }

    public function rememberToken(int $interviewId, int $userId, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_interview_video_tokens')->insertOrIgnore(['interview_id' => $interviewId, 'user_id' => $userId, 'created_at' => $this->format($at)]);
    }

    public function tokenUserIds(int $interviewId): array
    {
        return array_map('intval', $this->connection->table('recruitment_interview_video_tokens')->where('interview_id', $interviewId)->orderBy('id')->pluck('user_id')->all());
    }

    public function forgetTokens(int $interviewId): void
    {
        $this->connection->table('recruitment_interview_video_tokens')->where('interview_id', $interviewId)->delete();
    }

    public function dueForClosing(DateTimeImmutable $now): array
    {
        $endedBefore = $now->sub(new DateInterval('PT'.VideoJoinWindow::MINUTES_AFTER.'M'));

        return array_map('intval', $this->connection->table('recruitment_interviews')
            ->whereNotNull('video_room_id')
            ->whereNull('video_room_closed_at')
            ->where(fn ($query) => $query->where('status', '!=', InterviewStatus::Confirmed->value)->orWhere('ends_at', '<', $this->format($endedBefore)))
            ->orderBy('id')
            ->pluck('id')
            ->all());
    }

    private function time(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
