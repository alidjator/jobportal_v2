<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

interface ReviewRequestRepository
{
    /**
     * @param  list<int>  $applicationIds
     * @param  list<int>  $reviewerIds
     */
    public function add(ReviewRequest $request, array $applicationIds, array $reviewerIds): ReviewRequest;

    public function find(int $id, bool $forUpdate = false): ?ReviewRequest;

    /** The requests of a job, newest first. @return list<ReviewRequest> */
    public function forJob(int $jobId): array;

    /** The requests where the user is a reviewer, the latest due date first. @return list<ReviewRequest> */
    public function forReviewer(int $userId, int $companyId): array;

    /** The requests the user made, the latest due date first. @return list<ReviewRequest> */
    public function forRequester(int $userId, int $companyId): array;

    /**
     * @param  list<int>  $requestIds
     * @return array<int, list<int>> the application ids by request id, in the order they were added
     */
    public function applicationIds(array $requestIds): array;

    /**
     * @param  list<int>  $requestIds
     * @return array<int, list<int>> the reviewer ids by request id
     */
    public function reviewerIds(array $requestIds): array;

    /**
     * @param  list<int>  $requestIds
     * @return array<int, list<ReviewAnswer>> the answers by request id
     */
    public function answers(array $requestIds): array;

    /** Saves the answer, or replaces the reviewer's earlier answer about the same application of the request. */
    public function saveAnswer(ReviewAnswer $answer): void;

    public function setStatus(int $id, ReviewRequestStatus $status, DateTimeImmutable $at): void;

    /**
     * @param  list<int>  $ids
     * @return list<ReviewRequest>
     */
    public function findMany(array $ids): array;

    /**
     * @param  list<int>  $applicationIds
     * @return array<int, list<int>> the ids of the review requests that name each application
     */
    public function requestIdsForApplications(array $applicationIds): array;

    /** Open requests whose reminder was not sent and whose due day is `$lastDue` or earlier, but not before `$firstDue` (dates in Jakarta time). @return list<ReviewRequest> */
    public function dueForReminder(DateTimeImmutable $firstDue, DateTimeImmutable $lastDue): array;

    /** Marks the reminder as sent; false when another run did it first. */
    public function claimReminder(int $id, DateTimeImmutable $at): bool;

    /** Open requests that passed their due day (before `$today`, a date in Jakarta time) without the requester being told. @return list<ReviewRequest> */
    public function dueForOverdueNotice(DateTimeImmutable $today): array;

    /** Marks the overdue notice as sent; false when another run did it first. */
    public function claimOverdueNotice(int $id, DateTimeImmutable $at): bool;
}
