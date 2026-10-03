<?php

namespace Src\CandidateProfile\Application\Commands;

use Illuminate\Contracts\Hashing\Hasher;
use Src\CandidateProfile\Domain\AccountRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** POST /candidate/settings type=account-delete. */
class DeleteAccount
{
    public function __construct(private AccountRepository $accounts, private Hasher $hasher) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId, string $currentPassword): array
    {
        $hash = $this->accounts->passwordHash($userId);

        if (! $hash || ! $this->hasher->check($currentPassword, $hash)) {
            throw new BusinessRuleViolation('Current password does not match!');
        }

        $this->accounts->delete($userId);

        return ['message' => 'Account Deleted Successful!'];
    }
}
