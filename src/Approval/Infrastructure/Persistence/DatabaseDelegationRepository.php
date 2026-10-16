<?php

namespace Src\Approval\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Approval\Domain\Delegation;
use Src\Approval\Domain\DelegationRepository;
use stdClass;

final readonly class DatabaseDelegationRepository implements DelegationRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string DAY_FORMAT = 'Y-m-d';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $id): ?Delegation
    {
        $row = $this->connection->table('approval_delegations')->where('id', $id)->first();

        return $row === null ? null : $this->toDelegation($row);
    }

    public function create(Delegation $delegation): Delegation
    {
        $id = (int) $this->connection->table('approval_delegations')->insertGetId([
            'approver_user_id' => $delegation->approverUserId,
            'delegate_user_id' => $delegation->delegateUserId,
            'from_date' => $delegation->from->format(self::DAY_FORMAT),
            'to_date' => $delegation->to->format(self::DAY_FORMAT),
            'created_at' => $delegation->createdAt->format(self::TIME_FORMAT),
        ]);

        return $this->find($id) ?? throw new \LogicException("Delegation {$id} was just created.");
    }

    public function delete(int $id): void
    {
        $this->connection->table('approval_delegations')->where('id', $id)->delete();
    }

    public function ofApprover(int $approverUserId, DateTimeImmutable $today): array
    {
        return $this->connection->table('approval_delegations')
            ->where('approver_user_id', $approverUserId)->where('to_date', '>=', $today->format(self::DAY_FORMAT))
            ->orderBy('from_date')->orderBy('id')->get()
            ->map(fn (stdClass $row): Delegation => $this->toDelegation($row))
            ->all();
    }

    public function delegatorsOf(int $delegateUserId, DateTimeImmutable $day): array
    {
        return $this->connection->table('approval_delegations')
            ->where('delegate_user_id', $delegateUserId)->where('from_date', '<=', $day->format(self::DAY_FORMAT))->where('to_date', '>=', $day->format(self::DAY_FORMAT))
            ->pluck('approver_user_id')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();
    }

    private function toDelegation(stdClass $row): Delegation
    {
        return new Delegation(
            (int) $row->id, (int) $row->approver_user_id, (int) $row->delegate_user_id,
            new DateTimeImmutable((string) $row->from_date), new DateTimeImmutable((string) $row->to_date),
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        );
    }
}
