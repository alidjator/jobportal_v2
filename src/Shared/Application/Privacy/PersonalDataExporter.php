<?php

namespace Src\Shared\Application\Privacy;

/**
 * One context's part of the personal data export of a job seeker (`me/data-export`): what the context holds about this person, as plain values the person may
 * read. Each context tags its exporters with `PersonalDataExporter::class` in its provider, like the erasure steps. An exporter reads only its own context's tables,
 * leaves out what belongs to a company or to a psychologist (the boundary of `docs/legal-review.md` question 14), and never writes anything.
 */
interface PersonalDataExporter
{
    /** The key of this part in `data.json`; stable, because clients and the person's own tools read the file by it. */
    public function key(): string;

    public function export(int $userId): PersonalDataSection;
}
