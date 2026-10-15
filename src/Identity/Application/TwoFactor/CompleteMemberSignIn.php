<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Application\Auth\SignInUser;
use Src\Identity\Application\Auth\TwoFactorSignInResult;
use Src\Identity\Domain\UserType;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** The second step of a company member's sign-in (`POST auth/two-factor`): the usual 30-day token once the code of the second factor is right. */
final readonly class CompleteMemberSignIn
{
    public function __construct(private CompleteTwoFactorSignIn $complete) {}

    /**
     * @throws AuthenticationFailed
     * @throws TooManyAttempts
     * @throws AccessDenied
     */
    public function execute(CompleteTwoFactorSignInCommand $command): TwoFactorSignInResult
    {
        return $this->complete->execute($command, UserType::CompanyMember, SignInUser::TOKEN_LIFETIME);
    }
}
