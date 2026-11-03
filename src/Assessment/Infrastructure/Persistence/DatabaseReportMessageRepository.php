<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\ReportMessage;
use Src\Assessment\Domain\ReportMessageAuthor;
use Src\Assessment\Domain\ReportMessageRepository;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseReportMessageRepository implements ReportMessageRepository
{
    private const string TABLE = 'assessment_report_messages';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function add(ReportMessage $message): ReportMessage
    {
        $now = $this->clock->now();
        $id = $this->connection->table(self::TABLE)->insertGetId([
            'report_id' => $message->reportId,
            'author_kind' => $message->author->value,
            'author_user_id' => $message->authorUserId,
            'body' => $message->body,
            'created_at' => $now,
        ]);

        return new ReportMessage((int) $id, $message->reportId, $message->author, $message->authorUserId, $message->body, $now);
    }

    public function ofReport(int $reportId): array
    {
        return array_map($this->hydrate(...), $this->connection->table(self::TABLE)->where('report_id', $reportId)->orderBy('id')->get()->all());
    }

    public function askerUserIds(int $reportId): array
    {
        return array_map('intval', $this->connection->table(self::TABLE)
            ->where('report_id', $reportId)
            ->where('author_kind', ReportMessageAuthor::Company->value)
            ->distinct()
            ->pluck('author_user_id')
            ->all());
    }

    private function hydrate(stdClass $row): ReportMessage
    {
        return new ReportMessage(
            (int) $row->id,
            (int) $row->report_id,
            ReportMessageAuthor::from((string) $row->author_kind),
            (int) $row->author_user_id,
            (string) $row->body,
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        );
    }
}
