<?php

namespace Src\Identity\Infrastructure\Mail;

use Src\Identity\Application\IdentityEmailTemplates;

final class EmailVerificationMail extends VerificationCodeMail
{
    protected function templateKey(): string
    {
        return IdentityEmailTemplates::EMAIL_VERIFICATION;
    }
}
