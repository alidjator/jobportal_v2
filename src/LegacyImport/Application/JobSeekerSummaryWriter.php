<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;

/** Writes the import summaries of job seekers into the JobSeeker tables (the documented exception of this context). */
interface JobSeekerSummaryWriter
{
    /**
     * Saves the items of one job seeker: an item replaces the stored item with the same code, other stored items stay (a repeated run does not count twice).
     *
     * @param  list<array{code: string, count: int, detail?: array<string, int>}>  $items
     */
    public function save(int $userId, array $items, DateTimeImmutable $at): void;
}
