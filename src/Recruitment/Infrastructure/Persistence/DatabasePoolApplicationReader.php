<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Recruitment\Application\PoolApplicationReader;
use Src\Recruitment\Application\PoolApplicationRow;
use Src\Recruitment\Domain\StageType;
use stdClass;

final readonly class DatabasePoolApplicationReader implements PoolApplicationReader
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function facts(int $applicationId): ?array
    {
        $row = $this->connection->table('recruitment_applications')->where('id', $applicationId)->first(['company_id', 'user_id', 'stage_type', 'talent_pool_consent_at', 'applied_at']);

        return $row === null ? null : [
            'companyId' => (int) $row->company_id,
            'applicantUserId' => (int) $row->user_id,
            'stageType' => StageType::from($row->stage_type),
            'consentGiven' => $row->talent_pool_consent_at !== null,
            'appliedAt' => $this->time((string) $row->applied_at),
        ];
    }

    public function consentingUserIds(int $companyId, array $userIds, DateTimeImmutable $since): array
    {
        return array_map('intval', $this->consenting($companyId, $userIds, $since)->distinct()->pluck('user_id')->all());
    }

    public function latestConsenting(int $companyId, array $userIds, DateTimeImmutable $since): array
    {
        $latest = [];

        foreach ($this->consenting($companyId, $userIds, $since)->whereNotNull('profile_snapshot')
            ->selectRaw('DISTINCT ON (user_id) id, user_id, applied_at, profile_snapshot')->orderBy('user_id')->orderByDesc('applied_at')->orderByDesc('id')->get() as $row) {
            $snapshot = json_decode((string) $row->profile_snapshot, true, 512, JSON_THROW_ON_ERROR);

            $latest[(int) $row->user_id] = ['id' => (int) $row->id, 'appliedAt' => $this->time((string) $row->applied_at), 'snapshot' => is_array($snapshot) ? $snapshot : []];
        }

        return $latest;
    }

    public function latest(int $companyId, array $userIds): array
    {
        $latest = [];

        foreach ($this->connection->table('recruitment_applications')->where('company_id', $companyId)->whereIn('user_id', $userIds)
            ->selectRaw('DISTINCT ON (user_id) id, user_id, job_id, stage_type, applied_at, hiring_manager_visible_at')->orderBy('user_id')->orderByDesc('applied_at')->orderByDesc('id')->get() as $row) {
            $latest[(int) $row->user_id] = $this->toRow($row);
        }

        return $latest;
    }

    public function of(int $companyId, int $applicantUserId): array
    {
        return $this->connection->table('recruitment_applications')->where('company_id', $companyId)->where('user_id', $applicantUserId)
            ->orderByDesc('applied_at')->orderByDesc('id')->get(['id', 'job_id', 'stage_type', 'applied_at', 'hiring_manager_visible_at'])
            ->map(fn (stdClass $row): PoolApplicationRow => $this->toRow($row))->all();
    }

    /** @param  list<int>  $userIds */
    private function consenting(int $companyId, array $userIds, DateTimeImmutable $since): Builder
    {
        return $this->connection->table('recruitment_applications')
            ->where('company_id', $companyId)->whereIn('user_id', $userIds)
            ->whereNotNull('talent_pool_consent_at')->where('applied_at', '>=', $since->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT));
    }

    private function toRow(stdClass $row): PoolApplicationRow
    {
        return new PoolApplicationRow((int) $row->id, (int) $row->job_id, StageType::from($row->stage_type), $this->time((string) $row->applied_at), $row->hiring_manager_visible_at !== null);
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
