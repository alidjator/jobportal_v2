<?php

namespace Src\Job\Application\Admin;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface AdminJobReader
{
    /** The rows without their company (the use case adds it through Company). @return Page<AdminJobListItem> */
    public function page(AdminJobQuery $query, PageRequest $request): Page;

    /**
     * The title and description of every job waiting for review, keyed by job id, for the scan that puts the flagged ones first.
     *
     * @return array<int, array{title: string, description: ?string}>
     */
    public function pendingTexts(): array;
}
