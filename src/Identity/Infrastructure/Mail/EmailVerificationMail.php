<?php

namespace Src\Identity\Infrastructure\Mail;

final class EmailVerificationMail extends VerificationCodeMail
{
    protected function subjectKey(): string
    {
        return 'identity.email_verification.subject';
    }

    protected function markdownView(): string
    {
        return 'identity.email-verification';
    }
}
