<?php

namespace Src\Company\Application\Admin;

final readonly class CompanyCounts
{
    public function __construct(
        public int $members,
        public int $pendingInvitations,
    ) {}
}
