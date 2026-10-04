<?php

namespace Src\Platform\Application\Audit;

use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;

/** Reads the audit log for admins holding `audit_logs.view`. Reading it is not itself audited. */
final readonly class ListAuditLogs
{
    public function __construct(private AuditLogReader $reader) {}

    /** @return CursorPage<AuditLogRecord> */
    public function execute(AuditLogQuery $query, CursorRequest $request): CursorPage
    {
        return $this->reader->read($query, $request);
    }
}
