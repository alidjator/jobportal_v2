<?php

namespace Src\Shared\Application\Portal;

/**
 * Whether the groups of the ERP exist on this installation: the group, its legal entities and structure, its roles and settings, the group side of the workforce and the approvals, and the platform admin's pages for them. They do
 * not exist on a company portal of one company (`company` mode, scope `company`: a portal of one company has nothing to group); the marketplace and a company portal of a group have them
 * (`docs/design/career-modes.md`, decided 2026-10-28). Provided by the Platform context, which holds the portal identity.
 */
interface GroupFeature
{
    public function enabled(): bool;
}
