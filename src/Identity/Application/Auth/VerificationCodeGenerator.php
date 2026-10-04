<?php

namespace Src\Identity\Application\Auth;

interface VerificationCodeGenerator
{
    /** A new random code of six decimal digits (leading zeros kept), for example `049213`. */
    public function generate(): string;
}
