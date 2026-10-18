<?php

namespace Src\Platform\Application\EmailSuppression;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

/** Reads the suppression list for admins holding `settings.manage`, newest first, optionally narrowed to addresses containing the search text. */
final readonly class ListEmailSuppressions
{
    public function __construct(private EmailSuppressionRepository $suppressions) {}

    /** @return Page<EmailSuppression> */
    public function execute(?string $search, PageRequest $request): Page
    {
        return $this->suppressions->page($search === null || trim($search) === '' ? null : mb_strtolower(trim($search)), $request);
    }
}
