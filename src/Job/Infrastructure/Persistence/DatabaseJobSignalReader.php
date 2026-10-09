<?php

namespace Src\Job\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Job\Application\Matching\JobSignalReader;
use Src\Job\Domain\WorkMode;
use Src\Shared\Application\Matching\JobSignals;

/**
 * The signals of jobs in two queries: the job with the range of its experience level, the rank of its minimum education and the English (else Indonesian) name
 * of its role, and its skills. The "Fresh Graduated" level is told apart from "Less than a Year" by its slug.
 */
final readonly class DatabaseJobSignalReader implements JobSignalReader
{
    /** The slug of the master value "Fresh Graduated" (generated from its English name). */
    private const string FRESH_GRADUATE_SLUG = 'fresh-graduated';

    public function __construct(private ConnectionInterface $connection) {}

    public function forJobs(array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        $skills = [];

        foreach ($this->connection->table('job_job_skills')->whereIn('job_id', $jobIds)->get(['job_id', 'skill_id']) as $row) {
            $skills[(int) $row->job_id][] = (int) $row->skill_id;
        }

        $signals = [];

        foreach ($this->connection->table('job_jobs as j')
            ->leftJoin('reference_job_roles as r', 'r.id', '=', 'j.role_id')
            ->leftJoin('reference_experience_levels as x', 'x.id', '=', 'j.experience_level_id')
            ->leftJoin('reference_education_levels as e', 'e.id', '=', 'j.education_level_id')
            ->whereIn('j.id', $jobIds)
            ->get([
                'j.id', 'j.title', 'j.work_mode', 'j.province_id', 'j.city_id', 'j.salary_visible', 'j.salary_min', 'j.salary_max', 'j.salary_period_id',
                'r.name as role_name', 'x.slug as level_slug', 'x.min_years', 'x.max_years', 'e.rank as education_rank',
            ]) as $row) {
            $jobId = (int) $row->id;
            $hasLevel = $row->min_years !== null;
            $salaryMax = $row->salary_max ?? $row->salary_min;
            $showsSalary = (bool) $row->salary_visible && $salaryMax !== null && $row->salary_period_id !== null;

            $signals[$jobId] = new JobSignals(
                $jobId,
                (string) $row->title,
                $this->roleName($row->role_name),
                $skills[$jobId] ?? [],
                $hasLevel ? (int) $row->min_years : null,
                $row->max_years === null ? null : (int) $row->max_years,
                $hasLevel && $row->level_slug === self::FRESH_GRADUATE_SLUG,
                $row->education_rank === null ? null : (int) $row->education_rank,
                $row->work_mode === WorkMode::Remote->value,
                $row->province_id === null ? null : (int) $row->province_id,
                $row->city_id === null ? null : (int) $row->city_id,
                $showsSalary ? (int) $salaryMax : null,
                $showsSalary ? (int) $row->salary_period_id : null,
            );
        }

        return $signals;
    }

    private function roleName(mixed $json): ?string
    {
        if (! is_string($json)) {
            return null;
        }

        $names = json_decode($json, true);
        $name = is_array($names) ? ($names['en'] ?? $names['id'] ?? null) : null;

        return is_string($name) && $name !== '' ? $name : null;
    }
}
