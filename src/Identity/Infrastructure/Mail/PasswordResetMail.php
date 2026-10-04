<?php

namespace Src\Identity\Infrastructure\Mail;

use Src\Identity\Application\IdentityEmailTemplates;

final class PasswordResetMail extends VerificationCodeMail
{
    protected function templateKey(): string
    {
        return IdentityEmailTemplates::PASSWORD_RESET;
    }
}
