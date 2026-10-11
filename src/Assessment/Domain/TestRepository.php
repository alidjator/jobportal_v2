<?php

namespace Src\Assessment\Domain;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface TestRepository
{
    public function find(int $id, bool $forUpdate = false): ?Test;

    /** Inserts a new test or updates an existing one, and returns the stored test (with its id). */
    public function save(Test $test): Test;

    public function delete(int $id): void;

    /**
     * The tests of an owner, newest first.
     *
     * @return Page<Test>
     */
    public function pageOwnedBy(TestOwner $owner, ?TestStatus $status, ?string $search, PageRequest $request): Page;

    /** The company's tests that are not archived (an archived test frees its place). */
    public function countLiveOfCompany(int $companyId): int;

    /**
     * The published tests of the system library, by title.
     *
     * @return Page<Test>
     */
    public function pageLibrary(?string $search, PageRequest $request): Page;

    /**
     * The published batteries granted to the company, of providers that are active.
     *
     * @return Page<Test>
     */
    public function pageGrantedTo(int $companyId, ?string $search, PageRequest $request): Page;

    /**
     * Every published battery of the providers that are active, by title (an admin picks from it when granting).
     *
     * @return Page<Test>
     */
    public function pagePublishedBatteries(?string $search, PageRequest $request): Page;

    /** True when the company may use the published battery: it is granted and its provider is active. */
    public function batteryIsUsableBy(int $companyId, int $testId): bool;
}
