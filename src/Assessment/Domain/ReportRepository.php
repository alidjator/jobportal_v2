<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface ReportRepository
{
    public function find(int $id, bool $forUpdate = false): ?Report;

    public function findByAttempt(int $attemptId, bool $forUpdate = false): ?Report;

    /** Inserts a new report and returns it with its id. */
    public function add(Report $report): Report;

    /** Saves the text, the explanation, the notes and the signature. */
    public function update(Report $report): void;

    /** Keeps the version of a report that a revision replaces: its text, explanation, number and signer. */
    public function keepVersion(Report $report, DateTimeImmutable $supersededAt): void;

    /** The next number of the provider in the year; the caller holds the lock on the provider, so two signatures never take the same one. */
    public function nextSequence(int $providerId, int $year): int;

    /** The invitation the report belongs to. */
    public function invitationIdOf(int $reportId): ?int;

    /**
     * The provider's reports, oldest submission first; `$claimed` (`mine`, `others` or `none`, by live claim of `$psychologistId`) narrows them; `$reviewed` keeps those a psychologist has (true) or has not (false) reviewed, whatever the way they were signed.
     *
     * @return Page<ReportSummary>
     */
    public function pageOfProvider(int $providerId, ?ReportStatus $status, ?bool $reviewed, PageRequest $request, ?string $claimed = null, ?int $psychologistId = null): Page;

    /**
     * The provider's signed and automatically issued reports for the register, newest signature first.
     *
     * @return Page<RegisterEntry>
     */
    public function registerOfProvider(int $providerId, RegisterFilter $filter, PageRequest $request): Page;

    /** How many reports the register holds for the filter. */
    public function registerCount(int $providerId, RegisterFilter $filter): int;

    /**
     * The same reports as `registerOfProvider`, one by one and without a page (for an export): at most `$limit`, read in chunks so memory stays flat.
     *
     * @return iterable<RegisterEntry>
     */
    public function registerRows(int $providerId, RegisterFilter $filter, int $limit): iterable;

    /** Lets go of every report the psychologist holds (a suspended psychologist cannot finish them). */
    public function releaseClaimsOf(int $psychologistId): void;
}
