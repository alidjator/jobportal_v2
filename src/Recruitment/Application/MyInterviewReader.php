<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Reads the "my interviews" list. Provided by the persistence layer. */
interface MyInterviewReader
{
    /**
     * @return CursorPage<MyInterviewRow>
     *
     * @throws BusinessRuleViolation `invalid-cursor`
     */
    public function page(MyInterviewQuery $query, CursorRequest $request): CursorPage;
}
