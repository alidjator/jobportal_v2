<?php

namespace Src\Platform\Application\EmailSuppression;

use DateTimeImmutable;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

interface EmailSuppressionRepository
{
    /** Stores the address (lower case) unless it is already suppressed; returns the id of the new row, or null when the address was already suppressed. An existing entry is never overwritten. */
    public function add(string $email, EmailSuppressionReason $reason, ?string $source, ?string $details, DateTimeImmutable $at): ?int;

    public function find(int $id): ?EmailSuppression;

    public function delete(int $id): void;

    /** @return Page<EmailSuppression> newest first; `$search` matches part of the address */
    public function page(?string $search, PageRequest $request): Page;
}
