<?php

namespace Src\Platform\Application\Portal;

/** Tells the operations address about the career portal. */
interface PortalAlerts
{
    /** @param  list<PortalSkippedCompany>  $skipped companies the career site skips now */
    public function skipped(string $email, array $skipped): void;

    /** The career site shows every company it names again. */
    public function recovered(string $email): void;
}
