<?php

namespace Src\Identity\Application\TwoFactor;

use Src\Identity\Application\Auth\SignInPsychologist;
use Src\Identity\Application\Auth\TwoFactorSignInResult;
use Src\Identity\Domain\UserType;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** The second step of a psychologist's sign-in at the provider endpoints: the 12-hour token once the code of the second factor is right. */
final readonly class CompletePsychologistSignIn
{
    public const int RECOVERY_CODE_COUNT = CompleteTwoFactorSignIn::RECOVERY_CODE_COUNT;

    public function __construct(private CompleteTwoFactorSignIn $complete) {}

    /**
     * @throws AuthenticationFailed
     * @throws TooManyAttempts
     * @throws AccessDenied
     */
    public function execute(CompleteTwoFactorSignInCommand $command): TwoFactorSignInResult
    {
        return $this->complete->execute($command, UserType::Psychologist, SignInPsychologist::TOKEN_LIFETIME);
    }
}
