<?php

namespace Src\Identity\Infrastructure\Mail;

final class PasswordResetMail extends VerificationCodeMail
{
    protected function subjectKey(): string
    {
        return 'identity.password_reset.subject';
    }

    protected function markdownView(): string
    {
        return 'identity.password-reset';
    }
}
