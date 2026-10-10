<?php

namespace Src\Identity\Infrastructure\Auth;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;
use Src\Identity\Application\TwoFactor\TwoFactorAuthenticator;

/** TOTP with `pragmarx/google2fa` (30-second steps, 6 digits, one step of clock drift either way) and QR codes with `bacon/bacon-qr-code`. */
final readonly class Google2faTwoFactorAuthenticator implements TwoFactorAuthenticator
{
    private const int SECRET_LENGTH = 32;

    private const int QR_SIZE = 240;

    public function __construct(private string $issuer) {}

    public function generateSecret(): string
    {
        return (new Google2FA)->generateSecretKey(self::SECRET_LENGTH);
    }

    public function matchingStep(string $secret, string $code, ?int $afterStep): ?int
    {
        $step = (new Google2FA)->verifyKeyNewer($secret, $code, $afterStep ?? 0);

        return is_int($step) ? $step : null;
    }

    public function otpauthUrl(string $accountEmail, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($this->issuer), rawurlencode($accountEmail), $secret, rawurlencode($this->issuer),
        );
    }

    public function qrCode(string $otpauthUrl): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(self::QR_SIZE, 2), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($otpauthUrl));
    }
}
