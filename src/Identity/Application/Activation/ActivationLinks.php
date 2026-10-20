<?php

namespace Src\Identity\Application\Activation;

use Src\Identity\Domain\UserType;

/** The client pages the activation email links to; null while the page is not configured (the command then refuses to send). */
interface ActivationLinks
{
    public function activationUrl(UserType $audience): ?string;

    public function signInUrl(): ?string;
}
