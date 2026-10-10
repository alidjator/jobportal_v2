<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\CampaignWriter;

final readonly class DatabaseCampaignWriter implements CampaignWriter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function codeTaken(string $code): bool
    {
        return $this->connection->table('campaign_campaigns')->where('code', $code)->exists();
    }

    public function insert(int $companyId, string $code, string $name, string $type, ?string $startsOn, ?string $endsOn, int $creatorUserId, string $createdAt, string $updatedAt): int
    {
        return (int) $this->connection->table('campaign_campaigns')->insertGetId([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => true,
            'created_by_user_id' => $creatorUserId,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
    }
}
