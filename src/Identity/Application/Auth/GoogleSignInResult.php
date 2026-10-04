<?php

namespace Src\Identity\Application\Auth;

final readonly class GoogleSignInResult
{
    public function __construct(
        public UserSignInResult $signIn,
        public bool $isNewUser,
    ) {}
}
