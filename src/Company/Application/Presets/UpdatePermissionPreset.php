<?php

namespace Src\Company\Application\Presets;

use Src\Company\Application\Team\CompanyAdminGuard;
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
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A company admin renames a preset and changes its permissions. Members who got the set earlier keep what they have: a preset is copied when it is applied. */
final readonly class UpdatePermissionPreset
{
    public function __construct(private CompanyAdminGuard $guard, private PermissionPresetRepository $presets, private AuditTrail $audit, private TransactionManager $transactions, private Clock $clock) {}

    /**
     * @param  list<string>  $permissions  catalog names
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `permissions-required`, `permission-requires-view` or `permission-requires-review`
     * @throws StateConflict `permission-preset-name-taken`
     */
    public function execute(Actor $actor, int $id, string $name, array $permissions, ?string $ipAddress = null, ?string $userAgent = null): PermissionPreset
    {
        $companyId = (int) $this->guard->company($actor)->id;
        $now = $this->clock->now();

        return $this->transactions->run(function () use ($companyId, $id, $name, $permissions, $actor, $ipAddress, $userAgent, $now): PermissionPreset {
            $current = $this->presets->find($companyId, $id, forUpdate: true) ?? throw EntityNotFound::of('Permission preset', $id);
            $changed = $current->changed($name, $permissions, $now);

            $this->presets->save($changed);
            $this->audit->record(new AuditEntry(
                AuditActorType::User, $actor->id, AuditAction::COMPANY_PERMISSION_PRESET_UPDATED, 'permission_preset', $id,
                ['name' => $changed->name, 'permissions' => $changed->permissions], $ipAddress, $userAgent, $now, $companyId,
            ));

            return $changed;
        });
    }
}
