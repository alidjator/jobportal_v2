<?php

namespace Src\Platform\Application\Audit;

use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

interface AuditLogReader
{
    /**
     * Newest first (time, then id). The cursor is opaque to callers.
     *
     * @return CursorPage<AuditLogRecord>
     *
     * @throws BusinessRuleViolation `invalid-cursor` when the cursor was not produced by this list
     */
    public function read(AuditLogQuery $query, CursorRequest $request): CursorPage;
}
