<?php

namespace Src\Identity\Application\Activation;

use Src\Identity\Domain\ActivationKind;
use Src\Identity\Domain\ActivationVariant;
use Src\Identity\Domain\User;

interface ActivationEmailSender
{
    /** Hands the email to the mailer, in the language of the account. The text never holds a code or a password. */
    public function send(User $user, ActivationKind $kind, ActivationVariant $variant, string $companyName): void;
}
