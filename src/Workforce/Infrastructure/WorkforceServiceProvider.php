<?php

namespace Src\Workforce\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Shared\Application\Approval\ApprovalGranted;
use Src\Shared\Application\Approval\ApprovalRejected;
use Src\Shared\Application\Approval\ApprovalReturned;
use Src\Shared\Application\Approval\ApprovalSubjectCatalog;
use Src\Shared\Application\Company\CompanyLocationUsages;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Workforce\RequisitionGate;
use Src\Shared\Infrastructure\ContextServiceProvider;
use Src\Workforce\Application\Requisition\RequisitionGateService;
use Src\Workforce\Application\WorkforceApprovalSubjects;
use Src\Workforce\Application\WorkforceSettings;
use Src\Workforce\Domain\Events\PlanChanged;
use Src\Workforce\Domain\Events\RequisitionChanged;
use Src\Workforce\Domain\PlanRepository;
use Src\Workforce\Domain\RequisitionRepository;
use Src\Workforce\Infrastructure\Listeners\ApplyApprovalOutcome;
use Src\Workforce\Infrastructure\Listeners\RecordWorkforceAuditEntries;
use Src\Workforce\Infrastructure\Persistence\DatabasePlanRepository;
use Src\Workforce\Infrastructure\Persistence\DatabaseRequisitionRepository;
use Src\Workforce\Infrastructure\Persistence\RequisitionLocationUsage;

/** The Workforce context: manpower plans and requisitions of the internal tenants (`docs/design/erp-requisitions.md`). */
final class WorkforceServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        PlanRepository::class => DatabasePlanRepository::class,
        RequisitionRepository::class => DatabaseRequisitionRepository::class,
        RequisitionGate::class => RequisitionGateService::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...WorkforceSettings::definitions());
        });

        $this->callAfterResolving(CompanyLocationUsages::class, function (CompanyLocationUsages $usages): void {
            $usages->add($this->app->make(RequisitionLocationUsage::class));
        });

        $this->callAfterResolving(ApprovalSubjectCatalog::class, function (ApprovalSubjectCatalog $catalog): void {
            $catalog->add(...WorkforceApprovalSubjects::types());
        });
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(PlanChanged::class, [RecordWorkforceAuditEntries::class, 'onPlanChanged']);
        Event::listen(RequisitionChanged::class, [RecordWorkforceAuditEntries::class, 'onRequisitionChanged']);
        Event::listen(ApprovalGranted::class, [ApplyApprovalOutcome::class, 'onGranted']);
        Event::listen(ApprovalRejected::class, [ApplyApprovalOutcome::class, 'onRejected']);
        Event::listen(ApprovalReturned::class, [ApplyApprovalOutcome::class, 'onReturned']);
    }
}
