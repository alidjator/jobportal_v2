<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use JsonException;
use Src\Recruitment\Application\MyInterviewQuery;
use Src\Recruitment\Application\MyInterviewReader;
use Src\Recruitment\Application\MyInterviewRow;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\ScorecardStatus;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;

final readonly class DatabaseMyInterviewReader implements MyInterviewReader
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function page(MyInterviewQuery $query, CursorRequest $request): CursorPage
    {
        $now = $this->format($query->now);
        $final = [StageType::Rejected->value, StageType::Withdrawn->value, StageType::Hired->value];
        $builder = HiringManagerBound::limit($this->connection->table('recruitment_interviews as i')->join('recruitment_applications as a', 'a.id', '=', 'i.application_id'), $query->boundJobIds)
            ->where('a.company_id', $query->companyId)
            ->whereNotNull('a.profile_snapshot');

        if ($query->jobIds !== null) {
            $builder->whereIn('a.job_id', $query->jobIds);
        }

        if (! $query->allOfCompany || $query->scorecardPending) {
            $builder->where(fn (Builder $mine) => $mine->where('i.interviewer_id', $query->userId)->orWhereExists(
                fn (Builder $panel) => $panel->selectRaw('1')->from('recruitment_interview_participants as p')->whereColumn('p.interview_id', 'i.id')->where('p.user_id', $query->userId),
            ));
        }

        if ($query->upcoming) {
            $builder->whereIn('i.status', [InterviewStatus::Pending->value, InterviewStatus::Confirmed->value])->where('i.ends_at', '>', $now);
        } else {
            $builder->whereIn('i.status', [InterviewStatus::Confirmed->value, InterviewStatus::Completed->value])->where('i.ends_at', '<=', $now);
        }

        if ($query->scorecardPending) {
            $builder->whereIn('i.status', [InterviewStatus::Confirmed->value, InterviewStatus::Completed->value])
                ->where('i.starts_at', '<=', $now)
                ->whereNotIn('a.stage_type', $final)
                ->whereNotExists(fn (Builder $sent) => $sent->selectRaw('1')->from('recruitment_interview_scorecards as s')->whereColumn('s.interview_id', 'i.id')
                    ->where('s.member_id', $query->userId)->where('s.status', ScorecardStatus::Submitted->value));
        }

        if ($request->cursor !== null) {
            [$time, $id] = $this->decode($request->cursor);
            $comparison = $query->upcoming ? '>' : '<';
            $builder->where(fn (Builder $after) => $after
                ->where('i.starts_at', $comparison, $time)
                ->orWhere(fn (Builder $tie) => $tie->where('i.starts_at', '=', $time)->where('i.id', $comparison, $id)));
        }

        $direction = $query->upcoming ? 'asc' : 'desc';
        $rows = $builder->orderBy('i.starts_at', $direction)->orderBy('i.id', $direction)->limit($request->perPage + 1)->get([
            'i.id', 'i.application_id', 'a.job_id', 'a.stage_type', 'i.starts_at', 'i.ends_at', 'i.status', 'i.format', 'i.interviewer_id',
            new Expression("a.profile_snapshot->>'name' as candidate_name"),
        ])->all();

        $hasMore = count($rows) > $request->perPage;
        $rows = array_slice($rows, 0, $request->perPage);
        $last = $rows === [] ? null : $rows[array_key_last($rows)];
        $roles = $this->roles($rows, $query->userId);
        $sent = $this->sent($rows, $query->userId);

        return new CursorPage(
            array_map(fn (stdClass $row): MyInterviewRow => $this->toRow($row, $roles[(int) $row->id] ?? null, in_array((int) $row->id, $sent, true), $query->now), $rows),
            $hasMore && $last !== null ? $this->encode((string) $last->starts_at, (int) $last->id) : null,
            $request->perPage,
        );
    }

    /**
     * @param  list<stdClass>  $rows
     * @return array<int, string> `main` or `panel` by interview id, for the interviews the member sits on
     */
    private function roles(array $rows, int $userId): array
    {
        $roles = [];

        foreach ($rows as $row) {
            if ((int) $row->interviewer_id === $userId) {
                $roles[(int) $row->id] = 'main';
            }
        }

        $ids = array_map(static fn (stdClass $row): int => (int) $row->id, $rows);

        foreach ($this->connection->table('recruitment_interview_participants')->whereIn('interview_id', $ids)->where('user_id', $userId)->pluck('interview_id') as $id) {
            $roles[(int) $id] ??= 'panel';
        }

        return $roles;
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<int> the interviews whose scorecard the member sent
     */
    private function sent(array $rows, int $userId): array
    {
        return array_map('intval', $this->connection->table('recruitment_interview_scorecards')
            ->whereIn('interview_id', array_map(static fn (stdClass $row): int => (int) $row->id, $rows))
            ->where('member_id', $userId)->where('status', ScorecardStatus::Submitted->value)->pluck('interview_id')->all());
    }

    private function toRow(stdClass $row, ?string $role, bool $sent, DateTimeImmutable $now): MyInterviewRow
    {
        $status = InterviewStatus::from((string) $row->status);
        $startsAt = $this->time((string) $row->starts_at);
        $open = ($status === InterviewStatus::Confirmed || $status === InterviewStatus::Completed) && $startsAt <= $now
            && ! in_array((string) $row->stage_type, [StageType::Rejected->value, StageType::Withdrawn->value, StageType::Hired->value], true);

        return new MyInterviewRow(
            (int) $row->id,
            (int) $row->application_id,
            (int) $row->job_id,
            null,
            $row->candidate_name === null ? null : (string) $row->candidate_name,
            $startsAt,
            $this->time((string) $row->ends_at),
            $status,
            InterviewFormat::from((string) $row->format),
            $role,
            $role === null ? null : ($sent ? 'sent' : ($open ? 'pending' : 'not_open')),
        );
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function encode(string $time, int $id): string
    {
        return rtrim(strtr(base64_encode(json_encode([$time, $id], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{string, int} */
    private function decode(string $cursor): array
    {
        try {
            $decoded = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->invalid();
        }

        if (! is_array($decoded) || count($decoded) !== 2 || ! is_string($decoded[0]) || ! is_int($decoded[1]) || $decoded[1] < 1
            || DateTimeImmutable::createFromFormat(self::TIME_FORMAT, $decoded[0]) === false) {
            throw $this->invalid();
        }

        return [$decoded[0], $decoded[1]];
    }

    private function invalid(): BusinessRuleViolation
    {
        return new BusinessRuleViolation('The cursor is not valid. Start again without a cursor.', 'invalid-cursor');
    }
}
