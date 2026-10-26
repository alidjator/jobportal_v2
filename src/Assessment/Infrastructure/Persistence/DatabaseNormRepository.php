<?php

namespace Src\Assessment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\Norm;
use Src\Assessment\Domain\NormRepository;
use stdClass;

final readonly class DatabaseNormRepository implements NormRepository
{
    private const string TABLE = 'assessment_norms';

    public function __construct(private ConnectionInterface $connection) {}

    public function ofTest(int $testId): array
    {
        return array_map(
            static fn (stdClass $row): Norm => new Norm((string) $row->scale_key, (int) $row->raw_min, (int) $row->raw_max, (float) $row->standard_score, (string) $row->band, $row->explanation,
                $row->age_min === null ? null : (int) $row->age_min, $row->age_max === null ? null : (int) $row->age_max,
                $row->education_rank_min === null ? null : (int) $row->education_rank_min, $row->education_rank_max === null ? null : (int) $row->education_rank_max,
            ),
            $this->connection->table(self::TABLE)->where('test_id', $testId)->orderBy('scale_key')->orderBy('raw_min')->orderBy('id')->get()->all(),
        );
    }

    public function replace(int $testId, array $norms): void
    {
        $this->connection->table(self::TABLE)->where('test_id', $testId)->delete();

        if ($norms !== []) {
            $this->connection->table(self::TABLE)->insert(array_map(static fn (Norm $norm): array => [
                'test_id' => $testId,
                'scale_key' => $norm->scaleKey,
                'raw_min' => $norm->rawMin,
                'raw_max' => $norm->rawMax,
                'standard_score' => $norm->standardScore,
                'band' => $norm->band,
                'explanation' => $norm->explanation,
                'age_min' => $norm->ageMin,
                'age_max' => $norm->ageMax,
                'education_rank_min' => $norm->educationRankMin,
                'education_rank_max' => $norm->educationRankMax,
            ], $norms));
        }
    }
}
