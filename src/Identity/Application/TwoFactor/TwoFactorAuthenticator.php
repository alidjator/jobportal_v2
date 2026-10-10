<?php

namespace Src\Identity\Application\TwoFactor;

/** The time-based one-time password (TOTP) side of the second factor, behind a port so the library stays in Infrastructure. */
interface TwoFactorAuthenticator
{
    /** A new random secret (base32), shown to the admin once for their authenticator app. */
    public function generateSecret(): string;

    /**
     * The 30-second time step the code belongs to, or null when the code is wrong or its step is not newer than `$afterStep`
     * (so a code cannot be used twice). A small clock drift is tolerated.
     */
    public function matchingStep(string $secret, string $code, ?int $afterStep): ?int;

    /** The `otpauth://` address an authenticator app reads from a QR code. */
    public function otpauthUrl(string $accountEmail, string $secret): string;

    /** The QR code of an `otpauth://` address as an `data:image/svg+xml;base64,` image. */
    public function qrCode(string $otpauthUrl): string;
}
