<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/**
 * An application that came from the karir.pkp import and has not been moved since: its stage time only says when it was imported, so it has no time in stage and is never stale
 * until the team first changes its stage (`docs/design/phase-8-activation.md` section 6). The list filter and the task count apply the same rule in SQL (`UntouchedImportScope`); a test
 * keeps the two together.
 */
final class UntouchedImport
{
    public static function applies(?DateTimeImmutable $migratedAt, DateTimeImmutable $stageEnteredAt): bool
    {
        return $migratedAt !== null && $stageEnteredAt <= $migratedAt;
    }
}
