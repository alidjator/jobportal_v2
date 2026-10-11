<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptResult;
use Src\Assessment\Domain\IntegrityEventType;
use Src\Assessment\Domain\IntegrityTier;
use Src\Assessment\Domain\ResultBand;
use Src\Assessment\Domain\ResultStatus;
use Src\Shared\Application\Clock;

final readonly class DatabaseAttemptOutcomeRepository implements AttemptOutcomeRepository
{
    private const string EVENTS = 'assessment_integrity_events';

    private const string RESULTS = 'assessment_results';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function addEvent(int $attemptId, IntegrityEventType $type, DateTimeImmutable $at): int
    {
        $this->connection->table(self::EVENTS)->insert([
            'attempt_id' => $attemptId,
            'type' => $type->value,
            'occurred_at' => $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);

        return $this->eventCount($attemptId);
    }

    public function eventsOf(int $attemptId): array
    {
        $events = [];

        foreach ($this->connection->table(self::EVENTS)->where('attempt_id', $attemptId)->orderBy('occurred_at')->orderBy('id')->get() as $row) {
            $events[] = ['type' => IntegrityEventType::from((string) $row->type), 'occurred_at' => new DateTimeImmutable((string) $row->occurred_at, new DateTimeZone('UTC'))];
        }

        return $events;
    }

    public function eventCount(int $attemptId): int
    {
        return $this->connection->table(self::EVENTS)->where('attempt_id', $attemptId)->count();
    }

    public function saveResult(AttemptResult $result): void
    {
        $now = $this->clock->now();

        $values = [
            'status' => $result->status->value,
            'section_scores' => json_encode($result->sectionScores, JSON_THROW_ON_ERROR),
            'scale_scores' => $result->scaleScores === null ? null : json_encode($result->scaleScores, JSON_THROW_ON_ERROR),
            'earned_points' => $result->earnedPoints,
            'max_points' => $result->maxPoints,
            'percentage' => $result->percentage,
            'band' => $result->band?->value,
            'integrity_tier' => $result->integrityTier->value,
            'updated_at' => $now,
        ];

        $this->connection->table(self::RESULTS)->upsert(
            [['attempt_id' => $result->attemptId, 'created_at' => $now] + $values],
            ['attempt_id'],
            array_keys($values),
        );
    }

    public function resultOf(int $attemptId): ?AttemptResult
    {
        $row = $this->connection->table(self::RESULTS)->where('attempt_id', $attemptId)->first();

        if ($row === null) {
            return null;
        }

        return new AttemptResult(
            $attemptId,
            ResultStatus::from((string) $row->status),
            json_decode((string) $row->section_scores, true, flags: JSON_THROW_ON_ERROR),
            $row->scale_scores === null ? null : array_map('floatval', json_decode((string) $row->scale_scores, true, flags: JSON_THROW_ON_ERROR)),
            $row->earned_points === null ? null : (float) $row->earned_points,
            $row->max_points === null ? null : (float) $row->max_points,
            $row->percentage === null ? null : (float) $row->percentage,
            $row->band === null ? null : ResultBand::from((string) $row->band),
            IntegrityTier::from((string) $row->integrity_tier),
        );
    }
}
