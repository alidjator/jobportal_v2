<?php

namespace Src\Company\Application\Presets;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\Events\CompanyMemberPermissionsChanged;
use Src\Company\Domain\PermissionPresetRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin renames a preset and changes its permissions. Members who got the set earlier keep what they have: a preset is copied when it is applied.
 * With `$applyToMembers` the members who hold exactly the old set get the new one, each change audited like an edit of that member.
 */
final readonly class UpdatePermissionPreset
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private PermissionPresetRepository $presets,
        private CompanyMemberRepository $members,
        private PresetHolders $holders,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  list<string>  $permissions  catalog names
     * @param  bool  $applyToMembers  give the new set to the members who hold exactly the old one
     *
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `permissions-required`, `permission-requires-view` or `permission-requires-review`
     * @throws StateConflict `permission-preset-name-taken`
     */
    public function execute(Actor $actor, int $id, string $name, array $permissions, bool $applyToMembers = false, ?string $ipAddress = null, ?string $userAgent = null): PermissionPresetUsage
    {
        $companyId = (int) $this->guard->company($actor)->id;
        $now = $this->clock->now();

        [$usage, $events] = $this->transactions->run(function () use ($companyId, $id, $name, $permissions, $applyToMembers, $actor, $ipAddress, $userAgent, $now): array {
            $current = $this->presets->find($companyId, $id, forUpdate: true) ?? throw EntityNotFound::of('Permission preset', $id);
            $changed = $current->changed($name, $permissions, $now);
            $events = [];

            if ($applyToMembers) {
                foreach ($this->holders->userIds($companyId, $current->permissions) as $userId) {
                    $this->members->replacePermissions($companyId, $userId, $changed->permissions);
                    $events[] = new CompanyMemberPermissionsChanged($companyId, $userId, $current->permissions, $changed->permissions, (int) $actor->id, $ipAddress, $userAgent, $now);
                }
            }

            $this->presets->save($changed);
            $this->audit->record(new AuditEntry(
                AuditActorType::User, $actor->id, AuditAction::COMPANY_PERMISSION_PRESET_UPDATED, 'permission_preset', $id,
                ['name' => $changed->name, 'permissions' => $changed->permissions, 'applied_to_members' => count($events)], $ipAddress, $userAgent, $now, $companyId,
            ));

            return [new PermissionPresetUsage($changed, count($this->holders->userIds($companyId, $changed->permissions))), $events];
        });

        $this->events->dispatch($events);

        return $usage;
    }
}
