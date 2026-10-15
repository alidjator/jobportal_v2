<?php

namespace Src\Identity\Application\Account;

use DateTimeImmutable;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Application\Accounts\FirstSignIns;

/** Gives JobSeeker the accounts that signed in for the first time long enough ago to be reminded. */
final readonly class IdentityFirstSignIns implements FirstSignIns
{
    public function __construct(private UserRepository $users) {}

    public function jobSeekerIdsBefore(DateTimeImmutable $cutoff, int $afterId, int $limit): array
    {
        return $this->users->findJobSeekerIdsFirstSignedInBefore($cutoff, $afterId, $limit);
    }
}
