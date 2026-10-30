<?php

namespace Src\Company\Application\Presets;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\PermissionPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A company admin deletes a preset. Members who got its permissions keep them. */
final readonly class DeletePermissionPreset
{
    public function __construct(private CompanyAdminGuard $guard, private PermissionPresetRepository $presets, private AuditTrail $audit, private TransactionManager $transactions, private Clock $clock) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $companyId = (int) $this->guard->company($actor)->id;

        $this->transactions->run(function () use ($companyId, $id, $actor, $ipAddress, $userAgent): void {
            $current = $this->presets->find($companyId, $id, forUpdate: true) ?? throw EntityNotFound::of('Permission preset', $id);

            $this->presets->delete($companyId, $id);
            $this->audit->record(new AuditEntry(
                AuditActorType::User, $actor->id, AuditAction::COMPANY_PERMISSION_PRESET_DELETED, 'permission_preset', $id, ['name' => $current->name], $ipAddress, $userAgent, $this->clock->now(), $companyId,
            ));
        });
    }
}
