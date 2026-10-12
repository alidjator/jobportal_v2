<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\JobKitDefaults;

final readonly class DatabaseJobKitDefaults implements JobKitDefaults
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function forJob(int $jobId): array
    {
        $defaults = [];

        foreach ($this->connection->table('recruitment_job_stage_kits')->where('job_id', $jobId)->orderBy('stage_id')->get(['stage_id', 'kit_id']) as $row) {
            $defaults[(int) $row->stage_id] = (int) $row->kit_id;
        }

        return $defaults;
    }

    public function kitFor(int $jobId, int $stageId): ?int
    {
        $kitId = $this->connection->table('recruitment_job_stage_kits')->where('job_id', $jobId)->where('stage_id', $stageId)->value('kit_id');

        return $kitId === null ? null : (int) $kitId;
    }

    public function set(int $jobId, int $stageId, int $kitId, DateTimeImmutable $at): void
    {
        $time = $at->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);

        $this->connection->table('recruitment_job_stage_kits')->upsert(
            [['job_id' => $jobId, 'stage_id' => $stageId, 'kit_id' => $kitId, 'created_at' => $time, 'updated_at' => $time]],
            ['job_id', 'stage_id'],
            ['kit_id', 'updated_at'],
        );
    }

    public function remove(int $jobId, int $stageId): void
    {
        $this->connection->table('recruitment_job_stage_kits')->where('job_id', $jobId)->where('stage_id', $stageId)->delete();
    }
}
