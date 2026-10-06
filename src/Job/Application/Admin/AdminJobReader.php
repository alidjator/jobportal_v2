<?php

namespace Src\Job\Application\Admin;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface AdminJobReader
{
    /** The rows without their company (the use case adds it through Company). @return Page<AdminJobListItem> */
    public function page(AdminJobQuery $query, PageRequest $request): Page;
}
