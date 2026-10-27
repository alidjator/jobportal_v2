<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Application\ScreeningPresetListQuery;
use Src\Recruitment\Application\ScreeningPresetReader;
use Src\Recruitment\Domain\ScreeningPresetRepository;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Infrastructure\Persistence\TextSearch;

final readonly class DatabaseScreeningPresetReader implements ScreeningPresetReader
{
    public function __construct(private ConnectionInterface $connection, private ScreeningPresetRepository $presets) {}

    public function page(ScreeningPresetListQuery $query, PageRequest $request): Page
    {
        $filtered = $this->connection->table('recruitment_screening_presets')->where('company_id', $query->companyId);

        if ($query->search !== null && $query->search !== '') {
            TextSearch::contains($filtered, 'name', $query->search);
        }

        if ($query->jobLevelId !== null) {
            $filtered->where('job_level_id', $query->jobLevelId);
        }

        if ($query->firstForLevelId !== null) {
            $filtered->orderByRaw('case when job_level_id = ? then 0 else 1 end', [$query->firstForLevelId]);
        }

        $total = (clone $filtered)->count();
        $ids = array_map('intval', $filtered->orderByRaw('lower(name)')->orderBy('id')->offset($request->offset())->limit($request->perPage)->pluck('id')->all());
        $found = $this->presets->findMany($ids);

        return new Page(array_values(array_filter(array_map(static fn (int $id) => $found[$id] ?? null, $ids))), $total, $request);
    }
}
