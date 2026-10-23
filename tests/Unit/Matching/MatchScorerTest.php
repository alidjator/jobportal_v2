<?php

namespace Tests\Unit\Matching;

use PHPUnit\Framework\TestCase;
use Src\Matching\Application\MatchScorer;
use Src\Shared\Application\Matching\CandidateSignals;
use Src\Shared\Application\Matching\JobSignals;
use Src\Shared\Application\Matching\MatchScore;

final class MatchScorerTest extends TestCase
{
    /** @param  array<string, mixed>  $with */
    private function candidate(array $with = []): CandidateSignals
    {
        return new CandidateSignals(...[...[
            'userId' => 1, 'skillIds' => [], 'experienceMonths' => 28, 'freshGraduate' => false, 'educationRank' => null, 'provinceId' => 10, 'cityId' => 100,
            'expectedSalary' => null, 'expectedSalaryPeriodId' => null, 'titles' => [],
        ], ...$with]);
    }

    /** A job that says nothing but its title: every signal is left out until the test gives one. @param  array<string, mixed>  $with */
    private function job(array $with = []): JobSignals
    {
        return new JobSignals(...[...[
            'jobId' => 5, 'title' => 'Pq', 'roleName' => null, 'skillIds' => [], 'minYears' => null, 'maxYears' => null, 'freshGraduateLevel' => false,
            'minEducationRank' => null, 'remote' => false, 'provinceId' => null, 'cityId' => null, 'salaryMax' => null, 'salaryPeriodId' => null,
        ], ...$with]);
    }

    /** @param  array<string, mixed>  $candidate  @param  array<string, mixed>  $job */
    private function signal(string $name, array $candidate, array $job): ?int
    {
        return (new MatchScorer)->score($this->candidate($candidate), $this->job($job))->signals[$name];
    }

    public function test_skills_are_the_share_of_the_jobs_skills_the_candidate_has(): void
    {
        $this->assertSame(50, $this->signal('skills', ['skillIds' => [1, 2, 9]], ['skillIds' => [1, 2, 3, 4]]));
        $this->assertSame(100, $this->signal('skills', ['skillIds' => [1, 2, 3]], ['skillIds' => [1, 2]]));
        $this->assertSame(0, $this->signal('skills', ['skillIds' => []], ['skillIds' => [1]]));
        $this->assertNull($this->signal('skills', ['skillIds' => [1]], ['skillIds' => []]));
    }

    public function test_location_is_full_for_remote_or_the_same_city_half_for_the_same_province(): void
    {
        $this->assertSame(100, $this->signal('location', ['provinceId' => 99, 'cityId' => 999], ['remote' => true]));
        $this->assertSame(100, $this->signal('location', [], ['provinceId' => 10, 'cityId' => 100]));
        $this->assertSame(50, $this->signal('location', [], ['provinceId' => 10, 'cityId' => 101]));
        $this->assertSame(50, $this->signal('location', [], ['provinceId' => 10]));
        $this->assertSame(0, $this->signal('location', [], ['provinceId' => 11, 'cityId' => 110]));
        $this->assertNull($this->signal('location', [], []));
    }

    public function test_a_job_with_several_places_scores_the_best_of_them(): void
    {
        $places = [['province_id' => 11, 'city_id' => 110], ['province_id' => 10, 'city_id' => 100]];

        $this->assertSame(100, $this->signal('location', [], ['provinceId' => 11, 'cityId' => 110, 'places' => $places]));
        $this->assertSame(50, $this->signal('location', [], ['provinceId' => 11, 'cityId' => 110, 'places' => [['province_id' => 11, 'city_id' => 110], ['province_id' => 10, 'city_id' => 101]]]));
        $this->assertSame(0, $this->signal('location', [], ['provinceId' => 11, 'cityId' => 110, 'places' => [['province_id' => 11, 'city_id' => 110], ['province_id' => 12, 'city_id' => 120]]]));
        $this->assertNull($this->signal('location', [], ['places' => [['province_id' => null, 'city_id' => null]]]));
        $this->assertSame(100, $this->signal('location', [], ['remote' => true, 'places' => $places]));
    }

    public function test_experience_follows_the_range_of_the_level_and_never_punishes_more_experience(): void
    {
        // 28 months = 2.33 years.
        $this->assertSame(100, $this->signal('experience', [], ['minYears' => 1, 'maxYears' => 3]));
        $this->assertSame(100, $this->signal('experience', [], ['minYears' => 0, 'maxYears' => 1]));
        $this->assertSame(100, $this->signal('experience', [], ['minYears' => 1, 'maxYears' => 1]));
        $this->assertSame(78, $this->signal('experience', [], ['minYears' => 3, 'maxYears' => 5]));
        $this->assertSame(23, $this->signal('experience', [], ['minYears' => 10, 'maxYears' => null]));
        $this->assertNull($this->signal('experience', [], []));
    }

    public function test_the_fresh_graduated_level_tells_fresh_graduates_from_everyone_else(): void
    {
        $level = ['minYears' => 0, 'maxYears' => 1, 'freshGraduateLevel' => true];

        $this->assertSame(100, $this->signal('experience', ['freshGraduate' => true, 'experienceMonths' => 0], $level));
        $this->assertSame(50, $this->signal('experience', ['freshGraduate' => false, 'experienceMonths' => 11], $level));
        $this->assertSame(0, $this->signal('experience', ['freshGraduate' => false, 'experienceMonths' => 12], $level));
        // "Less than a Year" has the same range but is passed by anyone, a fresh graduate included.
        $this->assertSame(100, $this->signal('experience', ['freshGraduate' => true, 'experienceMonths' => 0], ['minYears' => 0, 'maxYears' => 1]));
    }

    public function test_education_is_full_at_the_minimum_rank_half_one_rank_below_and_nothing_below_that(): void
    {
        $this->assertSame(100, $this->signal('education', ['educationRank' => 3], ['minEducationRank' => 3]));
        $this->assertSame(100, $this->signal('education', ['educationRank' => 4], ['minEducationRank' => 3]));
        $this->assertSame(50, $this->signal('education', ['educationRank' => 2], ['minEducationRank' => 3]));
        $this->assertSame(0, $this->signal('education', ['educationRank' => 1], ['minEducationRank' => 3]));
        $this->assertSame(0, $this->signal('education', ['educationRank' => null], ['minEducationRank' => 3]));
        $this->assertNull($this->signal('education', ['educationRank' => 3], []));
    }

    public function test_salary_compares_the_expectation_with_the_maximum_in_the_same_period(): void
    {
        $job = ['salaryMax' => 10_000_000, 'salaryPeriodId' => 7];

        $this->assertSame(100, $this->signal('salary', ['expectedSalary' => 10_000_000, 'expectedSalaryPeriodId' => 7], $job));
        $this->assertSame(50, $this->signal('salary', ['expectedSalary' => 12_000_000, 'expectedSalaryPeriodId' => 7], $job));
        $this->assertSame(0, $this->signal('salary', ['expectedSalary' => 12_000_001, 'expectedSalaryPeriodId' => 7], $job));
        $this->assertNull($this->signal('salary', ['expectedSalary' => 5_000_000, 'expectedSalaryPeriodId' => 8], $job));
        $this->assertNull($this->signal('salary', ['expectedSalary' => null, 'expectedSalaryPeriodId' => null], $job));
        $this->assertNull($this->signal('salary', ['expectedSalary' => 5_000_000, 'expectedSalaryPeriodId' => 7], ['salaryMax' => null, 'salaryPeriodId' => 7]));
    }

    public function test_role_compares_the_words_of_the_role_and_title_with_the_words_of_the_candidates_titles(): void
    {
        $job = ['title' => 'Senior Backend Developer', 'roleName' => 'Backend Developer'];

        // Wanted words: backend, developer, senior.
        $this->assertSame(100, $this->signal('role', ['titles' => ['Backend developer', 'PHP programmer']], $job));
        $this->assertSame(50, $this->signal('role', ['titles' => ['Developer advocate']], $job));
        $this->assertSame(0, $this->signal('role', ['titles' => ['Accountant']], $job));
        $this->assertSame(0, $this->signal('role', ['titles' => []], $job));
        // Words shorter than three letters carry no meaning ("QA", "IT").
        $this->assertNull($this->signal('role', ['titles' => ['QA']], ['title' => 'QA']));
    }

    public function test_the_percentage_is_the_weighted_average_over_the_signals_that_could_be_judged(): void
    {
        // Skills 1/2 (40), same city (20), range passed (15), role words found (5): 20 + 20 + 15 + 5 = 60 of the 80 that could be judged.
        $score = (new MatchScorer)->score(
            $this->candidate(['skillIds' => [1], 'titles' => ['Backend Developer']]),
            $this->job(['title' => 'Backend Developer', 'roleName' => 'Backend Developer', 'skillIds' => [1, 2], 'provinceId' => 10, 'cityId' => 100, 'minYears' => 1, 'maxYears' => 3]),
        );

        $this->assertSame(75, $score->percent);
        $this->assertSame(['skills' => 50, 'location' => 100, 'experience' => 100, 'education' => null, 'salary' => null, 'role' => 100], $score->signals);
        $this->assertTrue($score->isRecommended());
    }

    public function test_a_job_that_says_too_little_has_no_percentage(): void
    {
        // Only the location (20) and the role (5) can be judged: under the 40 needed.
        $score = (new MatchScorer)->score($this->candidate(['titles' => ['Backend']]), $this->job(['title' => 'Backend', 'remote' => true]));

        $this->assertNull($score->percent);
        $this->assertSame(100, $score->signals['location']);
        $this->assertFalse($score->isRecommended());
    }

    public function test_forty_percent_is_the_least_that_is_recommended(): void
    {
        $this->assertTrue((new MatchScore(40, []))->isRecommended());
        $this->assertFalse((new MatchScore(39, []))->isRecommended());
        $this->assertFalse((new MatchScore(null, []))->isRecommended());
    }
}
