<?php

namespace Src\Platform\Interface\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Platform\Application\Audit\ListAuditLogs;
use Src\Platform\Interface\Http\Requests\ListAuditLogsRequest;
use Src\Platform\Interface\Http\Resources\AuditLogResource;
use Src\Shared\Interface\Http\ApiController;
use Src\Shared\Interface\Http\CursorResources;

/** Read-only: the audit log has no update or delete endpoint. */
final class AuditLogController extends ApiController
{
    public function index(ListAuditLogsRequest $request, ListAuditLogs $list): AnonymousResourceCollection
    {
        return CursorResources::of($list->execute($request->toQuery(), $request->cursorRequest()), AuditLogResource::class, $request);
    }
}
