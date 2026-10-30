<?php

namespace Src\Company\Application\Presets;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\PermissionPreset;
use Src\Company\Domain\PermissionPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company admin saves a set of permissions under a name, at most 20 per company (`phase-8-company-permissions.md`, changed 2026-10-30). */
final readonly class CreatePermissionPreset
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyRepository $companies,
        private PermissionPresetRepository $presets,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  list<string>  $permissions  catalog names
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws BusinessRuleViolation `permissions-required`, `permission-requires-view` or `permission-requires-review`
     * @throws StateConflict `permission-preset-name-taken` or `permission-preset-limit`
     */
    public function execute(Actor $actor, string $name, array $permissions, ?string $ipAddress = null, ?string $userAgent = null): PermissionPreset
    {
        $companyId = (int) $this->guard->company($actor)->id;
        $now = $this->clock->now();
        $preset = PermissionPreset::create($companyId, $name, $permissions, (int) $actor->id, $now);

        return $this->transactions->run(function () use ($preset, $companyId, $actor, $ipAddress, $userAgent, $now): PermissionPreset {
            $this->companies->lock($companyId);

            if ($this->presets->countOfCompany($companyId) >= PermissionPreset::MAX_PER_COMPANY) {
                throw new StateConflict('A company can save at most :max permission presets.', 'permission-preset-limit', parameters: ['max' => PermissionPreset::MAX_PER_COMPANY]);
            }

            $saved = $this->presets->add($preset);
            $this->audit->record(new AuditEntry(
                AuditActorType::User, $actor->id, AuditAction::COMPANY_PERMISSION_PRESET_CREATED, 'permission_preset', $saved->id,
                ['name' => $saved->name, 'permissions' => $saved->permissions], $ipAddress, $userAgent, $now, $companyId,
            ));

            return $saved;
        });
    }
}
