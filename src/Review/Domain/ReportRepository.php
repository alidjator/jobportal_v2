<?php

namespace Src\Review\Domain;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

interface ReportRepository
{
    /**
     * @throws StateConflict `already-reported`
     */
    public function add(Report $report): Report;

    /** Whether the reporter already has an open report on the review. */
    public function hasOpen(int $reviewId, ReporterKind $kind, int $reporterId): bool;

    public function update(Report $report): void;

    public function find(int $reportId, bool $forUpdate = false): ?Report;

    /** @return list<Report> */
    public function forReview(int $reviewId): array;

    /**
     * @param  list<int>  $reviewIds
     * @return array<int, int> number of open reports by review id; a review without one is missing
     */
    public function openCounts(array $reviewIds): array;

    /** Closes every open report of the review with the status (`actioned` when the review was taken down). */
    public function resolveOpen(int $reviewId, ReportStatus $status, int $adminId, DateTimeImmutable $now): void;

    /** Deletes the reports the user made, open or not. */
    public function deleteOfUser(int $userId): void;

    /** @return Page<Report> newest first */
    public function page(ReportStatus $status, PageRequest $request): Page;
}
