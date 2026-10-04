<?php

namespace Src\Identity\Infrastructure\Auth;

use Src\Identity\Application\Auth\VerificationCodeGenerator;

final class RandomVerificationCodeGenerator implements VerificationCodeGenerator
{
    public function generate(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }
}
