<?php

namespace Src\Workforce\Infrastructure;

use Illuminate\Support\Facades\Event;
use Src\Shared\Application\Approval\ApprovalGranted;
use Src\Shared\Application\Approval\ApprovalRejected;
use Src\Shared\Application\Approval\ApprovalReturned;
use Src\Shared\Application\Approval\ApprovalSubjectCatalog;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Infrastructure\ContextServiceProvider;
use Src\Workforce\Application\WorkforceApprovalSubjects;
use Src\Workforce\Application\WorkforceSettings;
use Src\Workforce\Domain\Events\PlanChanged;
use Src\Workforce\Domain\PlanRepository;
use Src\Workforce\Infrastructure\Listeners\ApplyApprovalOutcome;
use Src\Workforce\Infrastructure\Listeners\RecordWorkforceAuditEntries;
use Src\Workforce\Infrastructure\Persistence\DatabasePlanRepository;

/** The Workforce context: manpower plans and, later, requisitions of the internal tenants (`docs/design/erp-requisitions.md`). */
final class WorkforceServiceProvider extends ContextServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        PlanRepository::class => DatabasePlanRepository::class,
    ];

    public function register(): void
    {
        $this->callAfterResolving(SettingsCatalog::class, function (SettingsCatalog $catalog): void {
            $catalog->add(...WorkforceSettings::definitions());
        });

        $this->callAfterResolving(ApprovalSubjectCatalog::class, function (ApprovalSubjectCatalog $catalog): void {
            $catalog->add(...WorkforceApprovalSubjects::types());
        });
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(PlanChanged::class, [RecordWorkforceAuditEntries::class, 'onPlanChanged']);
        Event::listen(ApprovalGranted::class, [ApplyApprovalOutcome::class, 'onGranted']);
        Event::listen(ApprovalRejected::class, [ApplyApprovalOutcome::class, 'onRejected']);
        Event::listen(ApprovalReturned::class, [ApplyApprovalOutcome::class, 'onReturned']);
    }
}
