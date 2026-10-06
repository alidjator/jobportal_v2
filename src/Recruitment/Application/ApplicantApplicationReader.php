<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** Reads a job seeker's own applications. Nobody can read another person's. */
interface ApplicantApplicationReader
{
    /**
     * The applicant's applications, newest first (time applied, then id); `$status` keeps one stage type.
     *
     * @return CursorPage<ApplicationRow>
     *
     * @throws BusinessRuleViolation `invalid-cursor` when the cursor was not produced by this list
     */
    public function list(int $userId, ?StageType $status, CursorRequest $request): CursorPage;

    public function detail(int $userId, int $id): ?ApplicationDetailData;
}
