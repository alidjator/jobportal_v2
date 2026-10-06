<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScreeningQuestion;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface ScreeningQuestionReader
{
    /**
     * The newest questions first.
     *
     * @return Page<ScreeningQuestion>
     */
    public function page(ScreeningQuestionListQuery $query, PageRequest $request): Page;
}
