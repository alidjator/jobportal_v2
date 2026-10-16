<?php

namespace Src\Approval\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Approval\Domain\Events\ApprovalFlowChanged;
use Src\Approval\Domain\FlowRepository;
use Src\Approval\Infrastructure\Listeners\RecordApprovalAuditEntries;
use Src\Approval\Infrastructure\Persistence\DatabaseFlowRepository;
use Src\Shared\Infrastructure\ContextServiceProvider;

/** The Approval context: generic approval flows of groups and tenants for the subjects other contexts register (`docs/design/erp-requisitions.md` section 4). */
final class ApprovalServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        FlowRepository::class => DatabaseFlowRepository::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Event::listen(ApprovalFlowChanged::class, [RecordApprovalAuditEntries::class, 'onFlowChanged']);
    }
}
