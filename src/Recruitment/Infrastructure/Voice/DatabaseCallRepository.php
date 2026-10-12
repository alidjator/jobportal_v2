<?php

namespace Src\Recruitment\Infrastructure\Voice;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\Voice\CallRepository;
use Src\Recruitment\Domain\Voice\CallStatus;
use Src\Recruitment\Domain\Voice\InterviewCall;
use stdClass;

final readonly class DatabaseCallRepository implements CallRepository
{
    private const string TABLE = 'recruitment_interview_calls';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    /** The key of the advisory lock that serialises the capacity check. */
    private const string CAPACITY_LOCK = 'recruitment-voice-calls';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(InterviewCall $call): InterviewCall
    {
        $now = $this->format(new DateTimeImmutable);
        $id = (int) $this->connection->table(self::TABLE)->insertGetId([
            'interview_id' => $call->interviewId,
            'started_by' => $call->startedBy,
            'status' => $call->status->value,
            'number' => $call->number,
            'channel_id' => $call->channelId,
            'dialed_at' => $this->format($call->dialedAt),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find($id) ?? $call;
    }

    public function update(InterviewCall $call): void
    {
        $this->connection->table(self::TABLE)->where('id', $call->id)->update([
            'status' => $call->status->value,
            'answered_at' => $call->answeredAt === null ? null : $this->format($call->answeredAt),
            'ended_at' => $call->endedAt === null ? null : $this->format($call->endedAt),
            'duration_seconds' => $call->durationSeconds,
            'hangup_cause' => $call->hangupCause,
            'updated_at' => $this->format(new DateTimeImmutable),
        ]);
    }

    public function delete(int $callId): void
    {
        $this->connection->table(self::TABLE)->where('id', $callId)->delete();
    }

    public function find(int $callId): ?InterviewCall
    {
        $row = $this->connection->table(self::TABLE)->where('id', $callId)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByChannel(string $channelId, bool $forUpdate = false): ?InterviewCall
    {
        $query = $this->connection->table(self::TABLE)->where('channel_id', $channelId);
        $row = ($forUpdate ? $query->lockForUpdate() : $query)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function liveOf(int $interviewId): ?InterviewCall
    {
        $row = $this->connection->table(self::TABLE)->where('interview_id', $interviewId)->whereIn('status', $this->liveStatuses())->orderByDesc('id')->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function countOf(int $interviewId): int
    {
        return $this->connection->table(self::TABLE)->where('interview_id', $interviewId)->count();
    }

    public function forInterview(int $interviewId): array
    {
        return array_values(array_map(fn (stdClass $row): InterviewCall => $this->hydrate($row), $this->connection->table(self::TABLE)
            ->where('interview_id', $interviewId)->orderBy('id')->get()->all()));
    }

    public function liveCount(): int
    {
        return $this->connection->table(self::TABLE)->whereIn('status', $this->liveStatuses())->count();
    }

    public function lockCapacity(): void
    {
        $this->connection->selectOne('select pg_advisory_xact_lock(hashtext(?))', [self::CAPACITY_LOCK]);
    }

    public function secondsUsed(int $companyId, DateTimeImmutable $from, DateTimeImmutable $until, int $liveSeconds): int
    {
        $calls = $this->connection->table(self::TABLE.' as calls')
            ->join('recruitment_interviews as interviews', 'interviews.id', '=', 'calls.interview_id')
            ->join('recruitment_applications as applications', 'applications.id', '=', 'interviews.application_id')
            ->where('applications.company_id', $companyId)
            ->where('calls.answered_at', '>=', $this->format($from))
            ->where('calls.answered_at', '<', $this->format($until));

        $finished = (int) (clone $calls)->where('calls.status', CallStatus::Ended->value)->sum('calls.duration_seconds');
        $running = (clone $calls)->where('calls.status', CallStatus::Answered->value)->count();

        return $finished + $running * $liveSeconds;
    }

    public function liveBefore(DateTimeImmutable $dialedBefore): array
    {
        return array_values(array_map(fn (stdClass $row): InterviewCall => $this->hydrate($row), $this->connection->table(self::TABLE)
            ->whereIn('status', $this->liveStatuses())->where('dialed_at', '<', $this->format($dialedBefore))->orderBy('id')->get()->all()));
    }

    /** @return list<string> */
    private function liveStatuses(): array
    {
        return array_map(static fn (CallStatus $status): string => $status->value, array_values(array_filter(CallStatus::cases(), static fn (CallStatus $status): bool => $status->isLive())));
    }

    private function hydrate(stdClass $row): InterviewCall
    {
        $time = static fn (mixed $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return new InterviewCall(
            (int) $row->id, (int) $row->interview_id, (int) $row->started_by, CallStatus::from((string) $row->status), (string) $row->number, (string) $row->channel_id,
            new DateTimeImmutable((string) $row->dialed_at, new DateTimeZone('UTC')), $time($row->answered_at), $time($row->ended_at),
            $row->duration_seconds === null ? null : (int) $row->duration_seconds, $row->hangup_cause === null ? null : (int) $row->hangup_cause,
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }
}
