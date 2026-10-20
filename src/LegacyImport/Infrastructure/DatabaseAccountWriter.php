<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\AccountWriter;
use Src\LegacyImport\Application\NewAccount;

final readonly class DatabaseAccountWriter implements AccountWriter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function emailTaken(string $email): bool
    {
        return $this->connection->table('identity_users')->where('email', $email)->exists();
    }

    public function insert(NewAccount $account): int
    {
        return (int) $this->connection->table('identity_users')->insertGetId([
            'type' => $account->type,
            'name' => $account->name,
            'email' => $account->email,
            'password' => $account->passwordHash,
            'email_verified_at' => $account->emailVerifiedAt,
            'status' => $account->status,
            'locale' => $account->locale,
            'registration_campaign_code' => $account->campaignCode,
            'created_at' => $account->createdAt,
            'updated_at' => $account->updatedAt,
            'imported_at' => $account->importedAt,
        ]);
    }

    public function setAvatarIfEmpty(int $userId, string $path): bool
    {
        return $this->connection->table('identity_users')->where('id', $userId)->whereNull('avatar_path')->update(['avatar_path' => $path]) === 1;
    }
}
