<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Domain\GradeRepository;
use Src\Organization\Domain\LegalEntity;
use Src\Organization\Domain\PositionRepository;
use Src\Organization\Domain\PositionVersion;
use Src\Organization\Domain\Timeline;
use Src\Organization\Domain\UnitRepository;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What every new version of a position must satisfy: an active unit of the same entity that exists from the day the version starts, a grade range of active grades of
 * the group in rank order, a job role of the reference data, and a "reports to" position of the same entity that is active, exists from that day and does not lead
 * back to this position on any day of the version's period.
 */
final readonly class PositionVersionRules
{
    public function __construct(
        private PositionRepository $positions,
        private UnitRepository $units,
        private GradeRepository $grades,
        private ReferenceValues $references,
    ) {}

    /**
     * Puts the version into the position's timeline after checking it, and returns the timeline to store.
     *
     * @param  list<PositionVersion>  $current  the position's versions now (empty for a new position)
     * @return list<PositionVersion>
     *
     * @throws BusinessRuleViolation `invalid-unit`, `invalid-grade`, `invalid-grade-range`, `invalid-job-role`, `invalid-reports-to`, `reports-to-cycle` or `before-first-version`
     */
    public function timelineWith(LegalEntity $entity, int $positionId, array $current, PositionVersion $version): array
    {
        $unit = $this->units->find($version->unitId);

        if ($unit === null || $unit->legalEntityId !== $entity->id || ! $unit->isActive()
            || ! Timeline::startedBy($this->units->versionsOf($version->unitId), $version->validFrom)) {
            throw new BusinessRuleViolation('The unit is not an active unit of the legal entity that exists from the day of the change.', 'invalid-unit');
        }

        $this->assertGrades($entity, $version);

        if ($version->jobRoleId !== null && ! $this->references->isActive('job-roles', $version->jobRoleId)) {
            throw new BusinessRuleViolation('The job role is not an active job role.', 'invalid-job-role');
        }

        $timeline = Timeline::with($current, $version);
        $placed = Timeline::at($timeline, $version->validFrom) ?? $version;

        if ($placed->reportsToPositionId !== null) {
            $timelines = $this->positions->timelinesOfEntity((int) $entity->id);
            $timelines[$positionId] = $timeline;
            $boss = $this->positions->find($placed->reportsToPositionId);

            if ($boss === null || $boss->legalEntityId !== $entity->id || $boss->id === $positionId || ! $boss->isActive()
                || ! Timeline::startedBy($timelines[$placed->reportsToPositionId] ?? [], $placed->validFrom)) {
                throw new BusinessRuleViolation('The position it reports to is not an active position of the legal entity that exists from the day of the change.', 'invalid-reports-to');
            }

            if (Timeline::closesLoop($timelines, $positionId, $placed, static fn (PositionVersion $other): ?int => $other->reportsToPositionId)) {
                throw new BusinessRuleViolation('The position would report to itself through the chain.', 'reports-to-cycle');
            }
        }

        return $timeline;
    }

    /** @throws BusinessRuleViolation `invalid-grade` or `invalid-grade-range` */
    private function assertGrades(LegalEntity $entity, PositionVersion $version): void
    {
        $ranks = [];

        foreach ([$version->minGradeId, $version->maxGradeId] as $gradeId) {
            if ($gradeId === null) {
                continue;
            }

            $grade = $this->grades->find($gradeId);

            if ($grade === null || $grade->groupId !== $entity->groupId || ! $grade->isActive()) {
                throw new BusinessRuleViolation('The grade is not an active grade of the group.', 'invalid-grade');
            }

            $ranks[$gradeId] = $grade->rank;
        }

        if ($version->minGradeId !== null && $version->maxGradeId !== null && $ranks[$version->minGradeId] > $ranks[$version->maxGradeId]) {
            throw new BusinessRuleViolation('The lowest grade ranks above the highest grade.', 'invalid-grade-range');
        }
    }
}
