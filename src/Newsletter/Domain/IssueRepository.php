<?php

namespace Src\Newsletter\Domain;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface IssueRepository
{
    public function add(Issue $issue): Issue;

    public function update(Issue $issue): void;

    public function delete(int $id): void;

    public function find(int $id, bool $forUpdate = false): ?Issue;

    /** @return Page<Issue> */
    public function page(?IssueStatus $status, PageRequest $request): Page;

    /** Records that every delivery of the issue has been queued. */
    public function markDispatched(int $id, \DateTimeImmutable $now): void;

    /**
     * Sets `delivered` and `failed` from the deliveries and, when the issue was fully dispatched and none is still queued, finishes a sending issue (`sent`, `finished_at`).
     *
     * @return bool whether the issue is finished
     */
    public function refreshCounts(int $id, \DateTimeImmutable $now): bool;
}
