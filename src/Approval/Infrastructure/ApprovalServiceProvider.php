<?php

namespace Src\Approval\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Approval\Application\Instance\SubmitForApproval;
use Src\Approval\Domain\ApprovalInstanceRepository;
use Src\Approval\Domain\DelegationRepository;
use Src\Approval\Domain\Events\ApprovalChanged as ApprovalChangedEvent;
use Src\Approval\Domain\Events\ApprovalFlowChanged;
use Src\Approval\Domain\FlowRepository;
use Src\Approval\Infrastructure\Listeners\RecordApprovalAuditEntries;
use Src\Approval\Infrastructure\Listeners\VacateSlotsWhenMemberRemoved;
use Src\Approval\Infrastructure\Persistence\DatabaseApprovalInstanceRepository;
use Src\Approval\Infrastructure\Persistence\DatabaseDelegationRepository;
use Src\Approval\Infrastructure\Persistence\DatabaseFlowRepository;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Shared\Application\Approval\Approvals;
use Src\Shared\Infrastructure\ContextServiceProvider;

/** The Approval context: generic approval flows of groups and tenants for the subjects other contexts register (`docs/design/erp-requisitions.md` section 4). */
final class ApprovalServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        FlowRepository::class => DatabaseFlowRepository::class,
        ApprovalInstanceRepository::class => DatabaseApprovalInstanceRepository::class,
        DelegationRepository::class => DatabaseDelegationRepository::class,
        Approvals::class => SubmitForApproval::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Event::listen(ApprovalFlowChanged::class, [RecordApprovalAuditEntries::class, 'onFlowChanged']);
        Event::listen(ApprovalChangedEvent::class, [RecordApprovalAuditEntries::class, 'onApprovalChanged']);
        Event::listen(CompanyMemberRemoved::class, VacateSlotsWhenMemberRemoved::class);
    }
}
