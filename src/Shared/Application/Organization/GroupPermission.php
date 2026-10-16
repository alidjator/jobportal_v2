<?php

namespace Src\Shared\Application\Organization;

/**
 * What a group member may do across the tenants of their group (`docs/design/erp-organization.md` section 4). The catalog is code, not a table a group edits.
 * Later designs add `group.workforce.*`, `group.approvals.manage` and `group.settings.manage`.
 */
enum GroupPermission: string
{
    case StructureManage = 'group.structure.manage';
    case MembersManage = 'group.members.manage';
    case RecruitmentView = 'group.recruitment.view';
    case AuditView = 'group.audit.view';

    /** @return list<string> every permission name, in catalog order */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
