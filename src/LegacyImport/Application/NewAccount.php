<?php

namespace Src\LegacyImport\Application;

/** An account ready to be written: what the importer worked out from a legacy `users` row. */
final readonly class NewAccount
{
    public function __construct(
        public string $type,
        public string $name,
        public string $email,
        public ?string $passwordHash,
        public ?string $emailVerifiedAt,
        public string $status,
        public string $locale,
        public ?string $campaignCode,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
