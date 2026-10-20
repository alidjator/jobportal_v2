<?php

namespace Src\LegacyImport\Application;

/** Writes the categories and entries of the talent pool (the documented exception of this context). Times are `Y-m-d H:i:s` UTC. */
interface TalentPoolWriter
{
    /** How many categories the company has. */
    public function categoryCount(int $companyId): int;

    /** The id of the company's category with this name, whatever its case, or null. */
    public function findCategory(int $companyId, string $name): ?int;

    /** @return int the id of the new category */
    public function insertCategory(int $companyId, string $name, string $at): int;

    /**
     * Inserts an entry of the `legacy` source: no note, no application, no member who saved it. `$importedAt` is the import time (the retention counts from it), `$savedAt` the day the company saved the person.
     *
     * @return int|null the id of the new entry, or null when the company holds the candidate already
     */
    public function insertLegacyEntry(int $companyId, int $candidateUserId, ?int $categoryId, string $savedAt, string $importedAt): ?int;
}
