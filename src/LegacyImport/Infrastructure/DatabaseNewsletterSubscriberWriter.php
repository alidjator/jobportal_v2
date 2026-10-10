<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\NewsletterSubscriberWriter;

final readonly class DatabaseNewsletterSubscriberWriter implements NewsletterSubscriberWriter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function emailTaken(string $email): bool
    {
        return $this->connection->table('newsletter_subscribers')->where('email', $email)->exists();
    }

    public function insertConfirmed(string $email, string $locale, string $confirmedAt, string $updatedAt): int
    {
        return (int) $this->connection->table('newsletter_subscribers')->insertGetId([
            'email' => $email,
            'status' => 'confirmed',
            'locale' => $locale,
            'confirmed_at' => $confirmedAt,
            'created_at' => $confirmedAt,
            'updated_at' => $updatedAt,
        ]);
    }
}
