<?php

namespace Src\Matching\Application;

use Src\Matching\Domain\MatchSignal;
use Src\Shared\Application\Matching\CandidateSignals;
use Src\Shared\Application\Matching\JobSignals;
use Src\Shared\Application\Matching\MatchScore;
use Src\Shared\Application\Matching\MatchScoring;

/**
 * Weighted rules, no learning. Each signal gives a fit from 0 to 1, or nothing when the job (or the candidate, for the salary) does not say enough to judge
 * it; the score is the weighted average over the signals that could be judged. When they weigh less than `MIN_KNOWN_WEIGHT` in total, there is no percentage.
 */
final readonly class MatchScorer implements MatchScoring
{
    /** The least weight of judged signals for a percentage to mean anything. */
    private const int MIN_KNOWN_WEIGHT = 40;

    /** The shortest word that counts when titles are compared. */
    private const int MIN_WORD_LENGTH = 3;

    public function score(CandidateSignals $candidate, JobSignals $job): MatchScore
    {
        $fits = [
            MatchSignal::Skills->value => $this->skills($candidate, $job),
            MatchSignal::Location->value => $this->location($candidate, $job),
            MatchSignal::Experience->value => $this->experience($candidate, $job),
            MatchSignal::Education->value => $this->education($candidate, $job),
            MatchSignal::Salary->value => $this->salary($candidate, $job),
            MatchSignal::Role->value => $this->role($candidate, $job),
        ];

        $known = 0;
        $earned = 0.0;
        $signals = [];

        foreach (MatchSignal::cases() as $signal) {
            $fit = $fits[$signal->value];
            $signals[$signal->value] = $fit === null ? null : (int) round($fit * 100);

            if ($fit !== null) {
                $known += $signal->weight();
                $earned += $signal->weight() * $fit;
            }
        }

        return new MatchScore($known < self::MIN_KNOWN_WEIGHT ? null : (int) round(100 * $earned / $known), $signals);
    }

    private function skills(CandidateSignals $candidate, JobSignals $job): ?float
    {
        if ($job->skillIds === []) {
            return null;
        }

        return count(array_intersect($job->skillIds, $candidate->skillIds)) / count($job->skillIds);
    }

    private function location(CandidateSignals $candidate, JobSignals $job): ?float
    {
        if ($job->remote) {
            return 1.0;
        }

        if ($job->provinceId === null) {
            return null;
        }

        if ($job->cityId !== null && $candidate->cityId === $job->cityId) {
            return 1.0;
        }

        return $candidate->provinceId === $job->provinceId ? 0.5 : 0.0;
    }

    private function experience(CandidateSignals $candidate, JobSignals $job): ?float
    {
        if ($job->minYears === null) {
            return null;
        }

        if ($job->freshGraduateLevel) {
            return $candidate->freshGraduate ? 1.0 : ($candidate->experienceMonths < 12 ? 0.5 : 0.0);
        }

        // Being more experienced than the level asks for is not held against the candidate.
        return $job->minYears === 0 ? 1.0 : min(1.0, ($candidate->experienceMonths / 12) / $job->minYears);
    }

    private function education(CandidateSignals $candidate, JobSignals $job): ?float
    {
        if ($job->minEducationRank === null) {
            return null;
        }

        return match (true) {
            $candidate->educationRank === null => 0.0,
            $candidate->educationRank >= $job->minEducationRank => 1.0,
            $candidate->educationRank === $job->minEducationRank - 1 => 0.5,
            default => 0.0,
        };
    }

    private function salary(CandidateSignals $candidate, JobSignals $job): ?float
    {
        if ($job->salaryMax === null || $job->salaryPeriodId === null || $candidate->expectedSalary === null || $candidate->expectedSalaryPeriodId !== $job->salaryPeriodId) {
            return null;
        }

        return match (true) {
            $candidate->expectedSalary <= $job->salaryMax => 1.0,
            $candidate->expectedSalary * 10 <= $job->salaryMax * 12 => 0.5,
            default => 0.0,
        };
    }

    private function role(CandidateSignals $candidate, JobSignals $job): ?float
    {
        $wanted = $this->words(trim(($job->roleName ?? '').' '.$job->title));

        if ($wanted === []) {
            return null;
        }

        $found = count(array_intersect($wanted, $this->words(implode(' ', $candidate->titles))));

        return match (true) {
            $found * 2 >= count($wanted) => 1.0,
            $found > 0 => 0.5,
            default => 0.0,
        };
    }

    /** @return list<string> the distinct lower-case words of the text that are long enough */
    private function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, static fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD_LENGTH)));
    }
}
