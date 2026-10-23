<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\JobStageTest;
use Src\Assessment\Domain\JobStageTestRepository;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseJobStageTestRepository implements JobStageTestRepository
{
    private const string TABLE = 'assessment_job_stage_tests';

    private const string ASSESSORS = 'assessment_job_stage_test_assessors';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function find(int $jobId, int $stageId): ?JobStageTest
    {
        $row = $this->connection->table(self::TABLE)->where('job_id', $jobId)->where('stage_id', $stageId)->first();

        return $row === null ? null : $this->hydrate([$row])[0];
    }

    public function ofJob(int $jobId): array
    {
        return $this->hydrate($this->connection->table(self::TABLE)->where('job_id', $jobId)->orderBy('stage_id')->get()->all());
    }

    public function save(JobStageTest $default): void
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $values = [
            'test_id' => $default->testId,
            'due_days' => $default->dueDays,
            'advance_min_percentage' => $default->advanceMinPercentage,
            'advance_to_stage_id' => $default->advanceToStageId,
            'updated_by' => $default->updatedBy,
            'updated_at' => $now,
        ];

        $existing = $this->connection->table(self::TABLE)->where('job_id', $default->jobId)->where('stage_id', $default->stageId)->lockForUpdate()->value('id');

        if ($existing === null) {
            $id = (int) $this->connection->table(self::TABLE)->insertGetId([...$values, 'job_id' => $default->jobId, 'stage_id' => $default->stageId, 'created_at' => $now]);
        } else {
            $id = (int) $existing;
            $this->connection->table(self::TABLE)->where('id', $id)->update($values);
            $this->connection->table(self::ASSESSORS)->where('default_id', $id)->delete();
        }

        foreach ($default->assessorIds as $userId) {
            $this->connection->table(self::ASSESSORS)->insert(['default_id' => $id, 'user_id' => $userId]);
        }
    }

    public function delete(int $jobId, int $stageId): void
    {
        $this->connection->table(self::TABLE)->where('job_id', $jobId)->where('stage_id', $stageId)->delete();
    }

    public function copy(int $fromJobId, int $toJobId, int $updatedBy): void
    {
        foreach ($this->ofJob($fromJobId) as $default) {
            $this->save(new JobStageTest(
                $toJobId, $default->stageId, $default->testId, $default->dueDays, $default->assessorIds, $default->advanceMinPercentage, $default->advanceToStageId, $updatedBy,
            ));
        }
    }

    /**
     * @param  array<int, stdClass>  $rows
     * @return list<JobStageTest>
     */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $assessors = [];

        foreach ($this->connection->table(self::ASSESSORS)->whereIn('default_id', array_map(static fn (stdClass $row): int => (int) $row->id, $rows))->orderBy('id')->get() as $assessor) {
            $assessors[(int) $assessor->default_id][] = (int) $assessor->user_id;
        }

        return array_values(array_map(static fn (stdClass $row): JobStageTest => new JobStageTest(
            (int) $row->job_id,
            (int) $row->stage_id,
            (int) $row->test_id,
            (int) $row->due_days,
            $assessors[(int) $row->id] ?? [],
            $row->advance_min_percentage === null ? null : (int) $row->advance_min_percentage,
            $row->advance_to_stage_id === null ? null : (int) $row->advance_to_stage_id,
            (int) $row->updated_by,
            new DateTimeImmutable((string) $row->updated_at, new DateTimeZone('UTC')),
        ), $rows));
    }
}
