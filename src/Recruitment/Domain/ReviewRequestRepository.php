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
}
