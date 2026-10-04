<?php

namespace Src\Identity\Domain;

enum VerificationPurpose: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';
}
