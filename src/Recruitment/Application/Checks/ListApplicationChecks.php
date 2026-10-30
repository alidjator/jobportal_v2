<?php

namespace Src\Recruitment\Application\Checks;

use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\CheckStatus;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The checks of an application, for everyone who may see it. The outcome, the note and the file show only to a member with `checks.manage`; reading the outcome of a medical or police record check is audited. */
final readonly class ListApplicationChecks
{
    public function __construct(
        private ChecksAccess $access,
        private ApplicationCheckRepository $checks,
        private CheckTypeRepository $types,
        private AuditTrail $audit,
        private Clock $clock,
    ) {}

    /**
     * @return list<CheckView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `feature-disabled`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $team = $this->access->team($actor, $applicationId, manage: false);
        $canSee = $team->member->membership->can(CompanyPermission::ChecksManage);
        $checks = $this->checks->ofApplication($applicationId);
        $types = $this->types->byIds(array_values(array_unique(array_map(static fn (ApplicationCheck $check): int => $check->checkTypeId, $checks))));
        $views = [];

        foreach ($checks as $check) {
            $type = $types[$check->checkTypeId] ?? null;

            if ($type === null) {
                continue;
            }

            if ($canSee && $check->status === CheckStatus::Completed && $type->kind->auditsReads()) {
                $this->audit->record(new AuditEntry(
                    AuditActorType::User, $team->member->userId, AuditAction::CHECK_OUTCOME_VIEWED, 'application_check', $check->id, ['application_id' => $applicationId, 'kind' => $type->kind->value],
                    $ipAddress, $userAgent, $this->clock->now(), $team->member->companyId,
                ));
            }

            $views[] = new CheckView($check, $type, $canSee);
        }

        return $views;
    }
}
