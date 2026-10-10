<?php

namespace Src\Newsletter\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Src\Newsletter\Domain\DeliveryStatus;
use Src\Newsletter\Domain\Issue;
use Src\Newsletter\Domain\IssueRepository;
use Src\Newsletter\Domain\IssueStatus;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use stdClass;

final readonly class DatabaseIssueRepository implements IssueRepository
{
    private const string TABLE = 'newsletter_issues';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private Connection $connection) {}

    public function add(Issue $issue): Issue
    {
        $id = (int) $this->connection->table(self::TABLE)->insertGetId($this->values($issue));

        return $this->find($id) ?? $issue;
    }

    public function update(Issue $issue): void
    {
        $this->connection->table(self::TABLE)->where('id', $issue->id)->update($this->values($issue));
    }

    public function delete(int $id): void
    {
        $this->connection->table(self::TABLE)->where('id', $id)->delete();
    }

    public function find(int $id, bool $forUpdate = false): ?Issue
    {
        $query = $this->connection->table(self::TABLE)->where('id', $id);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toIssue($row);
    }

    public function page(?IssueStatus $status, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        $total = $builder->count();
        $rows = $builder->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map(fn (stdClass $row): Issue => $this->toIssue($row), $rows->all()), $total, $request);
    }

    public function markDispatched(int $id, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->where('id', $id)->update(['dispatched_at' => $now->format(self::TIME_FORMAT)]);
    }

    public function refreshCounts(int $id, DateTimeImmutable $now): bool
    {
        $prefix = $this->connection->getTablePrefix();
        $issues = $prefix.self::TABLE;
        $deliveries = $prefix.'newsletter_deliveries';
        $time = $now->format(self::TIME_FORMAT);

        // One statement, so concurrent deliveries each see a consistent picture: the counts come from the deliveries, and a sending issue that was fully
        // dispatched with nothing left queued is finished.
        $this->connection->update(
            "UPDATE \"{$issues}\" SET
                \"delivered\" = (SELECT count(*) FROM \"{$deliveries}\" WHERE \"issue_id\" = \"{$issues}\".\"id\" AND \"status\" = ?),
                \"failed\" = (SELECT count(*) FROM \"{$deliveries}\" WHERE \"issue_id\" = \"{$issues}\".\"id\" AND \"status\" = ?),
                \"status\" = CASE WHEN \"status\" = 'sending' AND \"dispatched_at\" IS NOT NULL AND NOT EXISTS (SELECT 1 FROM \"{$deliveries}\" WHERE \"issue_id\" = \"{$issues}\".\"id\" AND \"status\" = ?) THEN 'sent' ELSE \"status\" END,
                \"finished_at\" = CASE WHEN \"status\" = 'sending' AND \"dispatched_at\" IS NOT NULL AND NOT EXISTS (SELECT 1 FROM \"{$deliveries}\" WHERE \"issue_id\" = \"{$issues}\".\"id\" AND \"status\" = ?) THEN ? ELSE \"finished_at\" END
             WHERE \"id\" = ?",
            [DeliveryStatus::Sent->value, DeliveryStatus::Failed->value, DeliveryStatus::Queued->value, DeliveryStatus::Queued->value, $time, $id],
        );

        return $this->connection->table(self::TABLE)->where('id', $id)->where('status', IssueStatus::Sent->value)->exists();
    }

    /** @return array<string, mixed> */
    private function values(Issue $issue): array
    {
        $time = static fn (?DateTimeImmutable $moment): ?string => $moment?->format(self::TIME_FORMAT);

        return [
            'subject' => $issue->subject,
            'body' => $issue->body,
            'status' => $issue->status->value,
            'created_by_admin_id' => $issue->createdByAdminId,
            'sent_by_admin_id' => $issue->sentByAdminId,
            'started_at' => $time($issue->startedAt),
            'finished_at' => $time($issue->finishedAt),
            'recipients' => $issue->recipients,
            'delivered' => $issue->delivered,
            'failed' => $issue->failed,
            'created_at' => $time($issue->createdAt),
            'updated_at' => $time($issue->updatedAt),
        ];
    }

    private function toIssue(stdClass $row): Issue
    {
        $time = static fn (mixed $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return new Issue(
            (string) $row->subject,
            (string) $row->body,
            IssueStatus::from((string) $row->status),
            (int) $row->created_by_admin_id,
            $row->sent_by_admin_id === null ? null : (int) $row->sent_by_admin_id,
            $time($row->started_at),
            $time($row->finished_at),
            (int) $row->recipients,
            (int) $row->delivered,
            (int) $row->failed,
            $time($row->created_at) ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
            $time($row->updated_at) ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
            (int) $row->id,
        );
    }
}
