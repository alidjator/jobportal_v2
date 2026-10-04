<?php

namespace Src\Notification\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use JsonException;
use Src\Notification\Application\InApp\InAppNotification;
use Src\Notification\Application\InApp\InAppNotificationReader;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;

/**
 * Keyset pagination over (created_at, id), newest first: the cursor holds the time and id of the last notification of the previous
 * slice, so a slice never repeats or skips rows while new notifications arrive. Rows are always created by Laravel's notification
 * channel, which sets `created_at`.
 */
final readonly class DatabaseInAppNotificationReader implements InAppNotificationReader
{
    /** The short name Identity registers for public users in the polymorphic morph map. */
    private const string USER_MORPH_TYPE = 'user';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function read(int $userId, ?bool $read, CursorRequest $request): CursorPage
    {
        $builder = $this->owned($userId)
            ->select(['id', 'type', 'data', 'read_at', 'created_at'])
            ->when($read === true, fn (Builder $b) => $b->whereNotNull('read_at'))
            ->when($read === false, fn (Builder $b) => $b->whereNull('read_at'));

        if ($request->cursor !== null) {
            [$time, $id] = $this->decode($request->cursor);
            $builder->where(fn (Builder $after) => $after
                ->where('created_at', '<', $time)
                ->orWhere(fn (Builder $tie) => $tie->where('created_at', '=', $time)->where('id', '<', $id)));
        }

        $rows = $builder->orderByDesc('created_at')->orderByDesc('id')->limit($request->perPage + 1)->get()->all();

        $hasMore = count($rows) > $request->perPage;
        $rows = array_slice($rows, 0, $request->perPage);
        $last = $rows === [] ? null : $rows[array_key_last($rows)];

        return new CursorPage(
            array_map(fn (stdClass $row): InAppNotification => $this->toNotification($row), $rows),
            $hasMore && $last !== null ? $this->encode((string) $last->created_at, (string) $last->id) : null,
            $request->perPage,
        );
    }

    public function unreadCount(int $userId): int
    {
        return $this->owned($userId)->whereNull('read_at')->count();
    }

    private function owned(int $userId): Builder
    {
        return $this->connection->table('notifications')
            ->where('notifiable_type', self::USER_MORPH_TYPE)
            ->where('notifiable_id', $userId);
    }

    private function toNotification(stdClass $row): InAppNotification
    {
        $data = json_decode((string) $row->data, true, 512, JSON_THROW_ON_ERROR);
        $utc = new DateTimeZone('UTC');

        return new InAppNotification(
            (string) $row->id,
            (string) $row->type,
            is_array($data) ? $data : [],
            $row->read_at === null ? null : new DateTimeImmutable((string) $row->read_at, $utc),
            new DateTimeImmutable((string) $row->created_at, $utc),
        );
    }

    private function encode(string $time, string $id): string
    {
        return rtrim(strtr(base64_encode(json_encode([$time, $id], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{string, string} */
    private function decode(string $cursor): array
    {
        try {
            $decoded = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->invalid();
        }

        if (! is_array($decoded) || count($decoded) !== 2 || ! is_string($decoded[0]) || ! is_string($decoded[1])
            || DateTimeImmutable::createFromFormat(self::TIME_FORMAT, $decoded[0]) === false || strlen($decoded[1]) > 36) {
            throw $this->invalid();
        }

        return [$decoded[0], $decoded[1]];
    }

    private function invalid(): BusinessRuleViolation
    {
        return new BusinessRuleViolation('The cursor is not valid. Start again without a cursor.', 'invalid-cursor');
    }
}
