<?php

namespace Src\Identity\Application\TwoFactor;

/** What an admin without a confirmed second factor needs to set up their authenticator app. */
final readonly class TwoFactorEnrolment
{
    public function __construct(public string $secret, public string $otpauthUrl, public string $qrCode) {}
}
