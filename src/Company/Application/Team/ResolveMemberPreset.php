<?php

namespace Src\Company\Application\Team;

use Src\Company\Domain\CompanyRole;
use Src\Company\Domain\MemberPermissionRules;
use Src\Company\Domain\MemberPreset;
use Src\Company\Domain\PermissionPresetRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Turns the key of a preset into the role and permissions it stands for: a built-in preset (`recruiter`) or one the company saved (`saved_12`). Access is given
 * only through a preset, so this is the one place that decides what a member or an invitation gets. A preset is copied, never referenced.
 */
final readonly class ResolveMemberPreset
{
    public function __construct(private PermissionPresetRepository $saved) {}

    /** @throws BusinessRuleViolation `unknown-permission-preset` when the key is neither a built-in preset nor one of the company's own */
    public function execute(int $companyId, string $key): ResolvedPreset
    {
        $builtIn = MemberPreset::tryFrom($key);

        if ($builtIn !== null) {
            return new ResolvedPreset($builtIn->role(), $builtIn->role() === CompanyRole::CompanyAdmin ? [] : MemberPermissionRules::validated($builtIn->permissionNames()));
        }

        $saved = preg_match('/^saved_([1-9][0-9]{0,17})$/', $key, $matches) === 1 ? $this->saved->find($companyId, (int) $matches[1]) : null;

        if ($saved === null) {
            throw new BusinessRuleViolation('The permission preset does not exist.', 'unknown-permission-preset');
        }

        return new ResolvedPreset(CompanyRole::Member, MemberPermissionRules::validated($saved->permissions));
    }
}
