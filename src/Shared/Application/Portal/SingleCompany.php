<?php

namespace Src\Shared\Application\Portal;

/**
 * Whether this installation holds one company only: the installation type `single` (a company portal of one company, `docs/design/erp-installation-type.md`). A `single` installation refuses a second company and a
 * second tenant link; `multi` has no limit. Provided by the Platform context, which holds the portal identity.
 */
interface SingleCompany
{
    public function enforced(): bool;
}
