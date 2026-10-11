<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\CompanyBatteryRepository;
use Src\Assessment\Domain\TestRepository;

final readonly class DatabaseCompanyBatteryRepository implements CompanyBatteryRepository
{
    private const string TABLE = 'assessment_company_batteries';

    public function __construct(
        private ConnectionInterface $connection,
        private TestRepository $tests,
    ) {}

    public function grant(int $companyId, int $testId, int $adminId, DateTimeImmutable $now): void
    {
        $this->connection->table(self::TABLE)->insertOrIgnore([
            'company_id' => $companyId,
            'test_id' => $testId,
            'granted_by_admin_id' => $adminId,
            'created_at' => $now,
        ]);
    }

    public function revoke(int $companyId, int $testId): void
    {
        $this->connection->table(self::TABLE)->where('company_id', $companyId)->where('test_id', $testId)->delete();
    }

    public function grantedTo(int $companyId): array
    {
        $tests = [];

        foreach ($this->connection->table(self::TABLE)->where('company_id', $companyId)->orderBy('id')->pluck('test_id') as $testId) {
            $test = $this->tests->find((int) $testId);

            if ($test !== null) {
                $tests[] = $test;
            }
        }

        return $tests;
    }
}
