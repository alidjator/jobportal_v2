<?php

namespace Src\Platform\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use JsonException;
use Src\Platform\Application\Audit\AuditLogQuery;
use Src\Platform\Application\Audit\AuditLogReader;
use Src\Platform\Application\Audit\AuditLogRecord;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;

/**
 * Keyset pagination over (occurred_at, id), newest first: the cursor holds the time and id of the last entry of the previous slice,
 * so a slice never repeats or skips entries even while new ones are appended, and deep slices cost no more than the first.
 */
final readonly class DatabaseAuditLogReader implements AuditLogReader
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(private ConnectionInterface $connection) {}

    public function read(AuditLogQuery $query, CursorRequest $request): CursorPage
    {
        $builder = $this->connection->table('platform_audit_logs')->select([
            'id', 'actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'metadata', 'ip_address', 'user_agent', 'occurred_at', 'company_id',
        ]);

        $this->applyFilters($builder, $query);

        if ($request->cursor !== null) {
            [$time, $id] = $this->decode($request->cursor);
            $builder->where(fn (Builder $after) => $after
                ->where('occurred_at', '<', $time)
                ->orWhere(fn (Builder $tie) => $tie->where('occurred_at', '=', $time)->where('id', '<', $id)));
        }

        $rows = $builder->orderByDesc('occurred_at')->orderByDesc('id')->limit($request->perPage + 1)->get()->all();

        $hasMore = count($rows) > $request->perPage;
        $rows = array_slice($rows, 0, $request->perPage);
        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        return new CursorPage(
            array_map(fn (stdClass $row): AuditLogRecord => $this->toRecord($row), $rows),
            $hasMore && $last !== null ? $this->encode((string) $last->occurred_at, (int) $last->id) : null,
            $request->perPage,
        );
    }

    private function applyFilters(Builder $builder, AuditLogQuery $query): void
    {
        $builder
            ->when($query->action !== null, fn (Builder $b) => $b->where('action', $query->action))
            ->when($query->actorType !== null, fn (Builder $b) => $b->where('actor_type', $query->actorType?->value))
            ->when($query->actorId !== null, fn (Builder $b) => $b->where('actor_id', $query->actorId))
            ->when($query->subjectType !== null, fn (Builder $b) => $b->where('subject_type', $query->subjectType))
            ->when($query->subjectId !== null, fn (Builder $b) => $b->where('subject_id', $query->subjectId))
            ->when($query->companyId !== null, fn (Builder $b) => $b->where('company_id', $query->companyId))
            ->when($query->actions !== null, fn (Builder $b) => $b->whereIn('action', $query->actions))
            ->when($query->metadataUserId !== null, fn (Builder $b) => $b->whereRaw("(metadata::jsonb ->> 'user_id') = ?", [(string) $query->metadataUserId]))
            ->when($query->from !== null, fn (Builder $b) => $b->where('occurred_at', '>=', $query->from?->format(self::TIME_FORMAT)))
            ->when($query->to !== null, fn (Builder $b) => $b->where('occurred_at', '<=', $query->to?->format(self::TIME_FORMAT)));
    }

    private function toRecord(stdClass $row): AuditLogRecord
    {
        $metadata = $row->metadata === null ? [] : json_decode((string) $row->metadata, true, 512, JSON_THROW_ON_ERROR);

        return new AuditLogRecord(
            (int) $row->id,
            AuditActorType::from($row->actor_type),
            $row->actor_id === null ? null : (int) $row->actor_id,
            (string) $row->action,
            $row->subject_type === null ? null : (string) $row->subject_type,
            $row->subject_id === null ? null : (int) $row->subject_id,
            is_array($metadata) ? $metadata : [],
            $row->ip_address === null ? null : (string) $row->ip_address,
            $row->user_agent === null ? null : (string) $row->user_agent,
            new DateTimeImmutable((string) $row->occurred_at, new DateTimeZone('UTC')),
            $row->company_id === null ? null : (int) $row->company_id,
        );
    }

    private function encode(string $time, int $id): string
    {
        // PostgreSQL leaves out trailing zero fractions (`09:00:00`), so the time is written in the one format the cursor is read in.
        $time = (new DateTimeImmutable($time, new DateTimeZone('UTC')))->format(self::TIME_FORMAT);

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

        if (! is_array($decoded) || count($decoded) !== 2 || ! is_string($decoded[0]) || ! is_int($decoded[1])
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
