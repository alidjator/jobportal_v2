<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Application\Auth\MemberTokenLifetime;
use Src\Identity\Application\Auth\TwoFactorSignInResult;
use Src\Identity\Domain\UserType;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** The second step of a company member's sign-in (`POST auth/two-factor`): the usual token (30 days, 12 hours for a member of an internal tenant) once the code of the second factor is right. */
final readonly class CompleteMemberSignIn
{
    public function __construct(private CompleteTwoFactorSignIn $complete, private MemberTokenLifetime $lifetime) {}

    /**
     * @throws AuthenticationFailed
     * @throws TooManyAttempts
     * @throws AccessDenied
     */
    public function execute(CompleteTwoFactorSignInCommand $command): TwoFactorSignInResult
    {
        return $this->complete->execute($command, UserType::CompanyMember, $this->lifetime->forUser(...));
    }
}
