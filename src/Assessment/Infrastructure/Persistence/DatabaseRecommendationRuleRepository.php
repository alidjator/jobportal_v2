<?php

namespace Src\Assessment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\RecommendationRuleRepository;
use Src\Assessment\Domain\RecommendationRules;
use Src\Shared\Application\Clock;

final readonly class DatabaseRecommendationRuleRepository implements RecommendationRuleRepository
{
    private const string TABLE = 'assessment_recommendation_rules';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function ofTest(int $testId): ?RecommendationRules
    {
        $row = $this->connection->table(self::TABLE)->where('test_id', $testId)->first();

        if ($row === null) {
            return null;
        }

        /** @var list<string> $scales */
        $scales = json_decode((string) $row->key_scales, true) ?: [];

        return new RecommendationRules($scales, (float) $row->recommended_min, (float) $row->not_yet_below);
    }

    public function replace(int $testId, ?RecommendationRules $rules): void
    {
        if ($rules === null) {
            $this->connection->table(self::TABLE)->where('test_id', $testId)->delete();

            return;
        }

        $this->connection->table(self::TABLE)->upsert(
            [['test_id' => $testId, 'key_scales' => json_encode($rules->keyScales), 'recommended_min' => $rules->recommendedMin, 'not_yet_below' => $rules->notYetBelow, 'updated_at' => $this->clock->now()]],
            ['test_id'],
            ['key_scales', 'recommended_min', 'not_yet_below', 'updated_at'],
        );
    }
}
