<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\ApplicationJobSnapshots;

final readonly class DatabaseApplicationJobSnapshots implements ApplicationJobSnapshots
{
    private const string TABLE = 'recruitment_applications';

    public function __construct(private ConnectionInterface $connection) {}

    public function save(int $applicationId, array $snapshot): void
    {
        $this->connection->table(self::TABLE)->where('id', $applicationId)->whereNull('job_snapshot')->update([
            'job_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function find(int $applicationId): ?array
    {
        $value = $this->connection->table(self::TABLE)->where('id', $applicationId)->value('job_snapshot');

        if ($value === null) {
            return null;
        }

        $snapshot = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($snapshot) ? $snapshot : null;
    }

    public function withoutSnapshot(int $afterId, int $limit): array
    {
        return $this->connection->table(self::TABLE)->whereNull('job_snapshot')->whereNotNull('profile_snapshot')->where('id', '>', $afterId)->orderBy('id')->limit($limit)->get(['id', 'job_id'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'job_id' => (int) $row->job_id])
            ->all();
    }
}
