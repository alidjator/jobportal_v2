<?php

namespace Src\Company\Application\Admin;

use Src\Company\Domain\CompanyStatus;

/** What an admin asks of the company list. `search` matches the name or the slug, whatever the letter case. `linked` keeps the companies that are (true) or are not (false) the tenant of a legal entity of an ERP group. */
final readonly class CompanyListQuery
{
    public function __construct(
        public ?string $search = null,
        public ?CompanyStatus $status = null,
        public ?bool $verified = null,
        public CompanyListSort $sort = CompanyListSort::NewestFirst,
        public ?bool $linked = null,
    ) {}
}
