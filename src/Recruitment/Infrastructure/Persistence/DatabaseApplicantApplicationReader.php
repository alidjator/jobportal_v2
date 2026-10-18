<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use JsonException;
use Src\Recruitment\Application\ApplicantApplicationReader;
use Src\Recruitment\Application\ApplicationDetailData;
use Src\Recruitment\Application\ApplicationHistoryEntry;
use Src\Recruitment\Application\ApplicationRow;
use Src\Recruitment\Domain\HireDetails;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Domain\WithdrawalReason;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;

/**
 * Keyset pagination over (applied_at, id), newest first: the cursor holds the time and id of the last application of the previous slice, so a slice never
 * repeats or skips rows while new applications arrive.
 */
final readonly class DatabaseApplicantApplicationReader implements ApplicantApplicationReader
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private DatabaseApplicationAnswers $answers) {}

    public function list(int $userId, ?StageType $status, CursorRequest $request): CursorPage
    {
        $builder = $this->connection->table('recruitment_applications')
            ->where('user_id', $userId)
            ->when($status !== null, fn (Builder $b) => $b->where('stage_type', $status?->value));

        if ($request->cursor !== null) {
            [$time, $id] = $this->decode($request->cursor);
            $builder->where(fn (Builder $after) => $after
                ->where('applied_at', '<', $time)
                ->orWhere(fn (Builder $tie) => $tie->where('applied_at', '=', $time)->where('id', '<', $id)));
        }

        $rows = $builder->orderByDesc('applied_at')->orderByDesc('id')->limit($request->perPage + 1)->get(['id', 'job_id', 'company_id', 'stage_type', 'applied_at', 'viewed_at'])->all();

        $hasMore = count($rows) > $request->perPage;
        $rows = array_slice($rows, 0, $request->perPage);
        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        return new CursorPage(
            array_map(fn (stdClass $row): ApplicationRow => $this->toRow($row), $rows),
            $hasMore && $last !== null ? $this->encode((string) $last->applied_at, (int) $last->id) : null,
            $request->perPage,
        );
    }

    public function detail(int $userId, int $id): ?ApplicationDetailData
    {
        $row = $this->connection->table('recruitment_applications')->where('id', $id)->where('user_id', $userId)->first();

        if ($row === null) {
            return null;
        }

        return new ApplicationDetailData(
            $this->toRow($row),
            $row->cover_letter,
            $row->resume_path === null ? null : (string) $row->resume_name,
            $row->withdrawn_note,
            $this->history($id),
            $this->answers->forApplication($id, (int) $row->job_id),
            $row->join_date === null ? null : new HireDetails(new DateTimeImmutable((string) $row->join_date, new DateTimeZone('UTC')), $row->hire_note),
        );
    }

    /**
     * The history by stage type, oldest first. A move between two stages of the same type (an extra stage was deleted) shows nothing to the applicant,
     * so an entry that repeats the type before it is left out.
     *
     * @return list<ApplicationHistoryEntry>
     */
    private function history(int $applicationId): array
    {
        $entries = $this->connection->table('recruitment_application_stage_history')
            ->where('application_id', $applicationId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['to_type', 'actor_kind', 'rejection_reason', 'withdrawal_reason', 'created_at'])
            ->map(static fn (stdClass $entry): ApplicationHistoryEntry => new ApplicationHistoryEntry(
                StageType::from($entry->to_type),
                new DateTimeImmutable((string) $entry->created_at, new DateTimeZone('UTC')),
                StageMoveActor::from($entry->actor_kind),
                $entry->rejection_reason === null ? null : RejectionReason::from($entry->rejection_reason),
                $entry->withdrawal_reason === null ? null : WithdrawalReason::from($entry->withdrawal_reason),
            ))
            ->all();

        $shown = [];

        foreach ($entries as $entry) {
            if ($shown === [] || $shown[array_key_last($shown)]->status !== $entry->status) {
                $shown[] = $entry;
            }
        }

        return $shown;
    }

    private function toRow(stdClass $row): ApplicationRow
    {
        return new ApplicationRow(
            (int) $row->id,
            (int) $row->job_id,
            (int) $row->company_id,
            StageType::from($row->stage_type),
            new DateTimeImmutable((string) $row->applied_at, new DateTimeZone('UTC')),
            $row->viewed_at === null ? null : new DateTimeImmutable((string) $row->viewed_at, new DateTimeZone('UTC')),
        );
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
