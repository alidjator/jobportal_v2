<?php

namespace Src\Company\Application\Team;

use Src\Shared\Application\Pagination\Page;

/** One page of the team list and the number of members of the company, counted without the search ("N anggota tim"). */
final readonly class TeamList
{
    /** @param  Page<TeamRow>  $page */
    public function __construct(
        public Page $page,
        public int $memberCount,
    ) {}
}
