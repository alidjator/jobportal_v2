<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\TalentPoolWriter;

final readonly class DatabaseTalentPoolWriter implements TalentPoolWriter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function categoryCount(int $companyId): int
    {
        return $this->connection->table('talentpool_categories')->where('company_id', $companyId)->count();
    }

    public function findCategory(int $companyId, string $name): ?int
    {
        $id = $this->connection->table('talentpool_categories')->where('company_id', $companyId)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->value('id');

        return $id === null ? null : (int) $id;
    }

    public function insertCategory(int $companyId, string $name, string $at): int
    {
        return (int) $this->connection->table('talentpool_categories')->insertGetId(['company_id' => $companyId, 'name' => $name, 'created_at' => $at, 'updated_at' => $at]);
    }

    public function insertLegacyEntry(int $companyId, int $candidateUserId, ?int $categoryId, string $savedAt, string $importedAt): ?int
    {
        $inserted = $this->connection->table('talentpool_entries')->insertOrIgnore([
            'company_id' => $companyId,
            'candidate_user_id' => $candidateUserId,
            'category_id' => $categoryId,
            'note' => null,
            'source' => 'legacy',
            'application_id' => null,
            'saved_by_user_id' => null,
            'created_at' => $savedAt,
            'updated_at' => $importedAt,
        ]);

        return $inserted === 0 ? null : (int) $this->connection->table('talentpool_entries')->where('company_id', $companyId)->where('candidate_user_id', $candidateUserId)->value('id');
    }
}
