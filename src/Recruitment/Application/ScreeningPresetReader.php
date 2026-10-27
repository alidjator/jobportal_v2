<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningPreset;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface ScreeningPresetReader
{
    /**
     * By name, the presets of a given level hint first when the query asks for it.
     *
     * @return Page<ScreeningPreset>
     */
    public function page(ScreeningPresetListQuery $query, PageRequest $request): Page;
}
