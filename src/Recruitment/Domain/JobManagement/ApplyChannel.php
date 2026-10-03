<?php

namespace Src\Recruitment\Domain\JobManagement;

/** Cara melamar: `app` (aplikasi), `email` (wajib alamat email), `custom_url` (wajib URL). */
final class ApplyChannel
{
    public const APP = 'app';

    public const EMAIL = 'email';

    public const CUSTOM_URL = 'custom_url';

    private function __construct(
        public readonly string $type,
        public readonly ?string $email,
        public readonly ?string $url,
    ) {}

    public static function of(string $type, ?string $email = null, ?string $url = null): self
    {
        if (! in_array($type, [self::APP, self::EMAIL, self::CUSTOM_URL], true)) {
            throw new InvalidJobData('apply_on', 'The selected apply on is invalid.');
        }

        if ($type === self::EMAIL && ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new InvalidJobData('apply_email', 'The apply email field must be a valid email address.');
        }

        if ($type === self::CUSTOM_URL && ($url === null || ! filter_var($url, FILTER_VALIDATE_URL))) {
            throw new InvalidJobData('apply_url', 'The apply url field must be a valid URL.');
        }

        // Seperti aplikasi lama, email/url dibawa apa adanya (nilai yang tidak relevan tetap tersimpan).
        return new self($type, $email, $url);
    }
}
