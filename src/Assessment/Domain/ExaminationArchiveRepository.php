<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;

/** The archive of the raw data of erased psychological examinations. There is no update: rows are added, read for a documented request and purged. */
interface ExaminationArchiveRepository
{
    /**
     * The psychological attempts of the user that are not archived yet, with their raw data as it is live now.
     *
     * @return list<ExaminationSnapshot>
     */
    public function snapshotsOf(int $userId): array;

    public function add(ArchivedExamination $examination): void;

    /**
     * The archived examinations of a report number (the numbers start with the institution's code, so one is practically one row).
     *
     * @return list<ArchivedExamination>
     */
    public function findByReportNumber(string $reportNumber): array;

    /**
     * The archived examinations whose retention ended before the given day, oldest first.
     *
     * @return list<ArchivedExamination>
     */
    public function endedBefore(DateTimeImmutable $day, int $limit): array;

    public function remove(int $id): void;

    /** How many psychological attempts the user has, archived or not (what an erasure would keep). */
    public function psychologicalAttemptCount(int $userId): int;
}
