<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\ApplicationFacetRepository;
use Src\Recruitment\Domain\ApplicationFacets;
use Src\Recruitment\Domain\ApplicationSource;
use Src\Recruitment\Domain\FacetSource;

final readonly class DatabaseApplicationFacetRepository implements ApplicationFacetRepository
{
    private const string TABLE = 'recruitment_application_facets';

    public function __construct(private ConnectionInterface $connection) {}

    public function save(int $applicationId, int $companyId, int $jobId, DateTimeImmutable $appliedAt, ApplicationFacets $facets): void
    {
        $this->connection->table(self::TABLE)->upsert([[
            'application_id' => $applicationId,
            'company_id' => $companyId,
            'job_id' => $jobId,
            'applied_at' => $appliedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'province_id' => $facets->provinceId,
            'city_id' => $facets->cityId,
            'education_rank' => $facets->educationRank,
            'experience_months' => $facets->experienceMonths,
            'expected_salary_monthly' => $facets->expectedSalaryMonthly,
            // A PostgreSQL array literal; the column type turns the text into `integer[]`.
            'skill_ids' => '{'.implode(',', $facets->skillIds).'}',
            'search_text' => $facets->searchText,
            'from_invitation' => $facets->fromInvitation,
            'returning' => $facets->returning,
        ]], ['application_id'], ['company_id', 'job_id', 'applied_at', 'province_id', 'city_id', 'education_rank', 'experience_months', 'expected_salary_monthly', 'skill_ids', 'search_text', 'from_invitation', 'returning']);
    }

    public function hasEarlierApplication(int $userId, int $companyId, DateTimeImmutable $before, ?int $exceptApplicationId = null): bool
    {
        $moment = $before->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        return $this->connection->table('recruitment_applications')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->when($exceptApplicationId === null, fn ($query) => $query->where('applied_at', '<', $moment))
            ->when($exceptApplicationId !== null, fn ($query) => $query->where(fn ($earlier) => $earlier->where('applied_at', '<', $moment)->orWhere(fn ($same) => $same->where('applied_at', $moment)->where('id', '<', $exceptApplicationId))))
            ->exists();
    }

    public function sourcesWithoutFacets(int $afterId, int $limit): array
    {
        return $this->connection->table('recruitment_applications as a')
            ->leftJoin(self::TABLE.' as f', 'f.application_id', '=', 'a.id')
            ->whereNull('f.application_id')
            ->whereNotNull('a.profile_snapshot')
            ->where('a.id', '>', $afterId)
            ->orderBy('a.id')
            ->limit($limit)
            ->get(['a.id', 'a.company_id', 'a.job_id', 'a.user_id', 'a.applied_at', 'a.profile_snapshot', 'a.source'])
            ->map(fn (object $row): FacetSource => new FacetSource(
                (int) $row->id,
                (int) $row->company_id,
                (int) $row->job_id,
                (int) $row->user_id,
                new DateTimeImmutable((string) $row->applied_at, new DateTimeZone('UTC')),
                (array) json_decode((string) $row->profile_snapshot, true, 512, JSON_THROW_ON_ERROR),
                $row->source === ApplicationSource::Invitation->value,
            ))
            ->all();
    }
}
