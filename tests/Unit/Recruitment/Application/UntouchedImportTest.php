<?php

namespace Tests\Unit\Recruitment\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Application\UntouchedImport;

final class UntouchedImportTest extends TestCase
{
    public function test_an_application_made_here_is_never_an_untouched_import(): void
    {
        $this->assertFalse(UntouchedImport::applies(null, new DateTimeImmutable('2026-10-01 09:00:00')));
    }

    public function test_an_imported_application_is_untouched_until_its_stage_changes_after_the_import(): void
    {
        $imported = new DateTimeImmutable('2026-10-05 12:00:00');

        $this->assertTrue(UntouchedImport::applies($imported, new DateTimeImmutable('2026-08-01 08:00:00')), 'The stage time of an application imported at its first stage is its legacy time.');
        $this->assertTrue(UntouchedImport::applies($imported, $imported), 'An application placed at a later stage got the import time.');
        $this->assertFalse(UntouchedImport::applies($imported, new DateTimeImmutable('2026-10-05 12:00:01')), 'The first move after the import starts the clock.');
    }
}
