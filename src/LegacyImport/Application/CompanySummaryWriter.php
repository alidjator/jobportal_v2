<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;

/** Writes the import summaries of companies into the Company tables (the documented exception of this context). */
interface CompanySummaryWriter
{
    /**
     * Saves the items of one company: an item replaces the stored item with the same code, other stored items stay (a repeated run does not count twice).
     *
     * @param  list<array{code: string, count: int, detail?: array<string, int>}>  $items
     */
    public function save(int $companyId, array $items, DateTimeImmutable $at): void;
}
