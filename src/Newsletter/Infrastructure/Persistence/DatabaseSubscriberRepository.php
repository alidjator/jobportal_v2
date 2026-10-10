<?php

namespace Src\Newsletter\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Src\Newsletter\Domain\Subscriber;
use Src\Newsletter\Domain\SubscriberRepository;
use Src\Newsletter\Domain\SubscriberStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Infrastructure\Persistence\TextSearch;
use stdClass;

final readonly class DatabaseSubscriberRepository implements SubscriberRepository
{
    private const string TABLE = 'newsletter_subscribers';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private Connection $connection) {}

    public function findByEmail(string $email, bool $forUpdate = false): ?Subscriber
    {
        return $this->first($this->connection->table(self::TABLE)->where('email', $email), $forUpdate);
    }

    public function find(int $id, bool $forUpdate = false): ?Subscriber
    {
        return $this->first($this->connection->table(self::TABLE)->where('id', $id), $forUpdate);
    }

    public function findByConfirmTokenHash(string $hash, bool $forUpdate = false): ?Subscriber
    {
        return $this->first($this->connection->table(self::TABLE)->where('confirm_token_hash', $hash), $forUpdate);
    }

    public function add(Subscriber $subscriber): Subscriber
    {
        // ON CONFLICT keeps a concurrent insert of the same address from aborting the surrounding transaction.
        $values = $this->values($subscriber);
        $columns = implode(', ', array_map(static fn (string $column): string => '"'.$column.'"', array_keys($values)));
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $prefix = $this->connection->getTablePrefix();

        $rows = $this->connection->select(
            "INSERT INTO \"{$prefix}".self::TABLE."\" ({$columns}) VALUES ({$marks}) ON CONFLICT (\"email\") DO NOTHING RETURNING \"id\"",
            array_values($values),
        );

        if ($rows === []) {
            return $this->findByEmail($subscriber->email) ?? $subscriber;
        }

        return $this->find((int) $rows[0]->id) ?? $subscriber;
    }

    public function update(Subscriber $subscriber): void
    {
        $this->connection->table(self::TABLE)->where('id', $subscriber->id)->update($this->values($subscriber));
    }

    public function delete(int $id): void
    {
        $this->connection->table(self::TABLE)->where('id', $id)->delete();
    }

    public function page(?string $search, ?SubscriberStatus $status, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        if ($search !== null && $search !== '') {
            $builder->where(fn (Builder $query) => TextSearch::contains($query, 'email', $search));
        }

        $total = $builder->count();
        $rows = $builder->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map(fn (stdClass $row): Subscriber => $this->toSubscriber($row), $rows->all()), $total, $request);
    }

    public function countsByStatus(): array
    {
        $counts = array_fill_keys(array_map(static fn (SubscriberStatus $status): string => $status->value, SubscriberStatus::cases()), 0);

        foreach ($this->connection->table(self::TABLE)->selectRaw('status, count(*) as total')->groupBy('status')->get() as $row) {
            $counts[(string) $row->status] = (int) $row->total;
        }

        return $counts;
    }

    public function countConfirmed(): int
    {
        return $this->connection->table(self::TABLE)->where('status', SubscriberStatus::Confirmed->value)->count();
    }

    public function confirmedAfter(int $afterId, int $limit): array
    {
        return array_map(
            fn (stdClass $row): Subscriber => $this->toSubscriber($row),
            $this->connection->table(self::TABLE)->where('status', SubscriberStatus::Confirmed->value)->where('id', '>', $afterId)->orderBy('id')->limit($limit)->get()->all(),
        );
    }

    public function deleteExpiredPending(DateTimeImmutable $before): int
    {
        return $this->connection->table(self::TABLE)
            ->where('status', SubscriberStatus::Pending->value)
            ->where('confirm_expires_at', '<', $before->format(self::TIME_FORMAT))
            ->delete();
    }

    private function first(Builder $query, bool $forUpdate): ?Subscriber
    {
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toSubscriber($row);
    }

    /** @return array<string, mixed> */
    private function values(Subscriber $subscriber): array
    {
        $time = static fn (?DateTimeImmutable $moment): ?string => $moment?->format(self::TIME_FORMAT);

        return [
            'email' => $subscriber->email,
            'status' => $subscriber->status->value,
            'locale' => $subscriber->locale,
            'confirm_token_hash' => $subscriber->confirmTokenHash,
            'confirm_expires_at' => $time($subscriber->confirmExpiresAt),
            'confirmation_sent_at' => $time($subscriber->confirmationSentAt),
            'confirmed_at' => $time($subscriber->confirmedAt),
            'unsubscribed_at' => $time($subscriber->unsubscribedAt),
            'created_at' => $time($subscriber->createdAt),
            'updated_at' => $time($subscriber->updatedAt),
        ];
    }

    private function toSubscriber(stdClass $row): Subscriber
    {
        $time = static fn (mixed $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return new Subscriber(
            (string) $row->email,
            SubscriberStatus::from((string) $row->status),
            (string) $row->locale,
            $row->confirm_token_hash === null ? null : (string) $row->confirm_token_hash,
            $time($row->confirm_expires_at),
            $time($row->confirmation_sent_at),
            $time($row->confirmed_at),
            $time($row->unsubscribed_at),
            $time($row->created_at) ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
            $time($row->updated_at) ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
            (int) $row->id,
        );
    }
}
