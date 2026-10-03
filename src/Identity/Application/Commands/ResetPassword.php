<?php

namespace Src\Identity\Application\Commands;

use Src\Identity\Domain\AccessTokens;
use Src\Identity\Domain\PasswordResetCodes;
use Src\Identity\Domain\ResetCodeStatus;
use Src\Identity\Domain\UserRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final class ResetPassword
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetCodes $codes,
        private readonly AccessTokens $tokens,
    ) {}

    public function __invoke(string $email, string $code, string $newPassword): void
    {
        $userId = $this->users->findIdByEmail($email) ?? throw new EntityNotFound('Invalid code');

        match ($this->codes->consume($userId, $code)) {
            ResetCodeStatus::Invalid => throw new BusinessRuleViolation('Invalid code'),
            ResetCodeStatus::Expired => throw new BusinessRuleViolation('Code expired'),
            ResetCodeStatus::Valid => null,
        };

        $this->users->changePassword($userId, $newPassword);
        $this->tokens->revokeAll($userId);
    }
}
