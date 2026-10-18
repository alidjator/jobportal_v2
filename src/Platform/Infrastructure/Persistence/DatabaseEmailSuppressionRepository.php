<?php

namespace Src\Platform\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Platform\Application\EmailSuppression\EmailSuppression;
use Src\Platform\Application\EmailSuppression\EmailSuppressionReason;
use Src\Platform\Application\EmailSuppression\EmailSuppressionRepository;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Infrastructure\Persistence\TextSearch;

/** The suppression list: the repository the Platform use cases write through and the read port the other contexts use. */
final readonly class DatabaseEmailSuppressionRepository implements EmailSuppressionRepository, EmailSuppressions
{
    private const string TABLE = 'platform_email_suppressions';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(string $email, EmailSuppressionReason $reason, ?string $source, ?string $details, DateTimeImmutable $at): ?int
    {
        $added = $this->connection->table(self::TABLE)->insertOrIgnore([
            'email' => $email,
            'reason' => $reason->value,
            'source' => $source,
            'details' => $details,
            'created_at' => $at->format('Y-m-d H:i:s'),
        ]);

        return $added === 0 ? null : (int) $this->connection->table(self::TABLE)->where('email', $email)->value('id');
    }

    public function find(int $id): ?EmailSuppression
    {
        $row = $this->connection->table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : $this->toSuppression($row);
    }

    public function delete(int $id): void
    {
        $this->connection->table(self::TABLE)->where('id', $id)->delete();
    }

    public function page(?string $search, PageRequest $request): Page
    {
        $query = $this->connection->table(self::TABLE);

        if ($search !== null) {
            TextSearch::contains($query, 'email', $search);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->offset($request->offset())->limit($request->perPage)->get();

        return new Page($rows->map(fn (object $row): EmailSuppression => $this->toSuppression($row))->all(), $total, $request);
    }

    public function reason(string $email): ?string
    {
        $reason = $this->connection->table(self::TABLE)->where('email', mb_strtolower(trim($email)))->value('reason');

        return $reason === null ? null : (string) $reason;
    }

    public function reasons(array $emails): array
    {
        $emails = array_values(array_unique(array_map(fn (string $email): string => mb_strtolower(trim($email)), $emails)));

        if ($emails === []) {
            return [];
        }

        $reasons = [];

        foreach ($this->connection->table(self::TABLE)->whereIn('email', $emails)->get(['email', 'reason']) as $row) {
            $reasons[(string) $row->email] = (string) $row->reason;
        }

        return $reasons;
    }

    private function toSuppression(object $row): EmailSuppression
    {
        return new EmailSuppression(
            (int) $row->id,
            (string) $row->email,
            EmailSuppressionReason::from((string) $row->reason),
            $row->source === null ? null : (string) $row->source,
            $row->details === null ? null : (string) $row->details,
            new DateTimeImmutable((string) $row->created_at),
        );
    }
}
