<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageType;

/** One stage of the company's pipeline with the number of applications of a list in it. */
final readonly class StageCount
{
    public function __construct(public int $stageId, public StageType $type, public string $name, public int $count) {}

    /**
     * The pipeline columns: every stage of the company, in order, with its count (zero for a stage the counts do not name).
     *
     * @param  list<Stage>  $stages
     * @param  array<int, int>  $counts  by stage id
     * @return list<StageCount>
     */
    public static function forStages(array $stages, array $counts): array
    {
        return array_map(static fn (Stage $stage): StageCount => new StageCount((int) $stage->id, $stage->type, $stage->name, $counts[(int) $stage->id] ?? 0), $stages);
    }
}
