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

    public function firstSignedInAt(array $userIds): array
    {
        $times = [];

        foreach ($this->users->findByIds($userIds) as $id => $user) {
            if ($user->firstLoginAt() !== null) {
                $times[$id] = $user->firstLoginAt();
            }
        }

        return $times;
    }
}
