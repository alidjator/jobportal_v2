<?php

namespace Src\CandidateProfile\Application\Commands;

use Illuminate\Contracts\Hashing\Hasher;
use Src\CandidateProfile\Domain\AccountRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/** POST /candidate/settings type=password. */
class ChangePassword
{
    public function __construct(private AccountRepository $accounts, private Hasher $hasher) {}

    /** @return array<string,mixed> */
    public function __invoke(int $userId, string $currentPassword, string $newPassword): array
    {
        $hash = $this->accounts->passwordHash($userId);

        if (! $hash || ! $this->hasher->check($currentPassword, $hash)) {
            throw new BusinessRuleViolation('Current password does not match!');
        }

        $this->accounts->changePassword($userId, $this->hasher->make($newPassword));

        return ['message' => 'Password Updated Successful!'];
    }
}
