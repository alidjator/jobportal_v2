<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Application\JakartaToday;
use Src\JobSeeker\Domain\CareerSummary;
use Src\Shared\Application\Matching\CandidateSignals;
use Src\Shared\Application\Matching\CandidateSignalSource;

/**
 * The signals of the matching engine, read in a handful of queries for any number of users: the profile, the skills, the experience periods, the education
 * entries and the titles. It reads no contact data and no protected attribute.
 */
final readonly class DatabaseCandidateSignalSource implements CandidateSignalSource
{
    public function __construct(private ConnectionInterface $connection, private JakartaToday $today) {}

    public function forUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $profiles = CompleteProfiles::query($this->connection)->whereIn('p.user_id', $userIds)
            ->get(['p.user_id', 'p.headline', 'p.province_id', 'p.city_id', 'p.expected_salary', 'p.expected_salary_period_id']);
        $ids = $profiles->map(fn (object $row): int => (int) $row->user_id)->all();

        if ($ids === []) {
            return [];
        }

        $skills = [];

        foreach ($this->connection->table('jobseeker_skills')->whereIn('user_id', $ids)->get(['user_id', 'skill_id']) as $row) {
            $skills[(int) $row->user_id][] = (int) $row->skill_id;
        }

        $periods = [];
        $titles = [];
        $utc = new DateTimeZone('UTC');

        foreach ($this->connection->table('jobseeker_experiences')->whereIn('user_id', $ids)->get(['user_id', 'job_title', 'start_date', 'end_date', 'is_current']) as $row) {
            $periods[(int) $row->user_id][] = [
                'start' => new DateTimeImmutable((string) $row->start_date, $utc),
                'end' => $row->is_current || $row->end_date === null ? null : new DateTimeImmutable((string) $row->end_date, $utc),
            ];
            $titles[(int) $row->user_id][] = (string) $row->job_title;
        }

        $educations = [];
        $ranks = [];

        foreach ($this->connection->table('jobseeker_educations as d')
            ->join('reference_education_levels as el', 'el.id', '=', 'd.education_level_id')
            ->whereIn('d.user_id', $ids)->get(['d.user_id', 'd.end_year', 'd.is_current', 'el.rank']) as $row) {
            $educations[(int) $row->user_id][] = ['end_year' => $row->end_year === null ? null : (int) $row->end_year, 'is_current' => (bool) $row->is_current];
            $ranks[(int) $row->user_id] = max($ranks[(int) $row->user_id] ?? 0, (int) $row->rank);
        }

        $today = $this->today->date();
        $year = $this->today->year();
        $signals = [];

        foreach ($profiles as $row) {
            $userId = (int) $row->user_id;
            $months = CareerSummary::monthsWorked($periods[$userId] ?? [], $today);

            $signals[$userId] = new CandidateSignals(
                $userId,
                $skills[$userId] ?? [],
                $months,
                CareerSummary::isFreshGraduate($months, $educations[$userId] ?? [], $year),
                $ranks[$userId] ?? null,
                $row->province_id === null ? null : (int) $row->province_id,
                $row->city_id === null ? null : (int) $row->city_id,
                $row->expected_salary === null ? null : (int) $row->expected_salary,
                $row->expected_salary_period_id === null ? null : (int) $row->expected_salary_period_id,
                array_values(array_filter([...($titles[$userId] ?? []), $row->headline])),
            );
        }

        return $signals;
    }

    public function userIdsAfter(int $afterId, int $limit): array
    {
        return CompleteProfiles::query($this->connection)->where('p.user_id', '>', $afterId)->orderBy('p.user_id')->limit($limit)->pluck('p.user_id')
            ->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}
