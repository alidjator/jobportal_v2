<?php

namespace Src\Organization\Infrastructure\Persistence;

use DateTimeImmutable;
use Src\Organization\Domain\PositionRepository;
use Src\Organization\Domain\Timeline;
use Src\Shared\Application\Organization\PositionDirectory;
use Src\Shared\Application\Organization\PositionSummary;

final readonly class RepositoryPositionDirectory implements PositionDirectory
{
    public function __construct(private PositionRepository $positions) {}

    public function ofEntity(int $legalEntityId, DateTimeImmutable $on): array
    {
        $timelines = $this->positions->timelinesOfEntity($legalEntityId);
        $summaries = [];

        foreach ($this->positions->ofEntity($legalEntityId) as $position) {
            $id = (int) $position->id;
            $version = Timeline::at($timelines[$id] ?? [], $on);

            if ($version !== null) {
                $summaries[$id] = new PositionSummary(
                    $id, $legalEntityId, $position->code, $version->title, $version->unitId, $version->minGradeId, $version->maxGradeId, $version->jobRoleId, $position->isActive(),
                );
            }
        }

        return $summaries;
    }
}
