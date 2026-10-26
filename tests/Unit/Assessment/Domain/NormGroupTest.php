<?php

namespace Tests\Unit\Assessment\Domain;

use PHPUnit\Framework\TestCase;
use Src\Assessment\Domain\Norm;
use Src\Assessment\Domain\NormCandidate;
use Src\Assessment\Domain\NormConverter;
use Src\Assessment\Domain\NormRules;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class NormGroupTest extends TestCase
{
    private function row(string $band, ?int $ageMin = null, ?int $ageMax = null, ?int $rankMin = null, ?int $rankMax = null, int $rawMin = 0, int $rawMax = 10): Norm
    {
        return new Norm('a', $rawMin, $rawMax, 50, $band, null, $ageMin, $ageMax, $rankMin, $rankMax);
    }

    public function test_a_row_without_a_group_applies_to_everyone_even_to_a_candidate_with_no_data(): void
    {
        $this->assertSame('General', NormConverter::convert([$this->row('General')], 'a', 5, new NormCandidate)?->band);
        $this->assertSame('General', NormConverter::convert([$this->row('General')], 'a', 5, new NormCandidate(30, 4))?->band);
    }

    public function test_a_restricted_row_needs_the_candidate_to_carry_that_data_and_to_fit(): void
    {
        $norms = [$this->row('Young', 18, 25)];

        $this->assertSame('Young', NormConverter::convert($norms, 'a', 5, new NormCandidate(18))?->band);
        $this->assertSame('Young', NormConverter::convert($norms, 'a', 5, new NormCandidate(25))?->band);
        $this->assertNull(NormConverter::convert($norms, 'a', 5, new NormCandidate(26)));
        $this->assertNull(NormConverter::convert($norms, 'a', 5, new NormCandidate));
    }

    public function test_the_most_specific_row_wins(): void
    {
        $norms = [
            $this->row('General'),
            $this->row('Age', 18, 40),
            $this->row('Narrow age', 18, 25),
            $this->row('Both', 18, 40, 3, 5),
        ];
        $pick = static fn (?int $age, ?int $rank): ?string => NormConverter::convert($norms, 'a', 5, new NormCandidate($age, $rank))?->band;

        $this->assertSame('Both', $pick(30, 4));
        $this->assertSame('Narrow age', $pick(20, 9));
        $this->assertSame('Age', $pick(35, null));
        $this->assertSame('General', $pick(60, 4));
        $this->assertSame('General', $pick(null, null));
    }

    public function test_a_row_whose_raw_range_does_not_hold_the_score_never_wins(): void
    {
        $norms = [$this->row('Specific high', 18, 25, rawMin: 6, rawMax: 10), $this->row('General low', rawMin: 0, rawMax: 5)];

        $this->assertSame('General low', NormConverter::convert($norms, 'a', 2, new NormCandidate(20))?->band);
        $this->assertSame('Specific high', NormConverter::convert($norms, 'a', 8, new NormCandidate(20))?->band);
        $this->assertNull(NormConverter::convert($norms, 'a', 8, new NormCandidate(40)));
    }

    public function test_the_group_of_a_row_is_reported_and_a_general_row_has_none(): void
    {
        $this->assertNull($this->row('G')->group());
        $this->assertSame(['age_min' => 18, 'age_max' => 25, 'education_rank_min' => 3, 'education_rank_max' => null], $this->row('Y', 18, 25, 3)->group());
    }

    public function test_rows_of_one_group_may_not_overlap_but_groups_may(): void
    {
        $this->assertCount(2, NormRules::check([$this->row('A', 18, 25), $this->row('B', 26, 40, rawMin: 0)], ['a']));
        $this->assertCount(2, NormRules::check([$this->row('General'), $this->row('Young', 18, 25)], ['a']));

        $this->expectException(BusinessRuleViolation::class);
        NormRules::check([$this->row('A', 18, 25), $this->row('B', 18, 25, rawMin: 10, rawMax: 20)], ['a']);
    }

    public function test_a_group_that_is_upside_down_or_out_of_range_is_refused(): void
    {
        foreach ([[30, 20], [0, 121], [-1, 10]] as [$min, $max]) {
            try {
                NormRules::check([$this->row('A', $min, $max)], ['a']);
                $this->fail("The age group {$min} to {$max} was accepted.");
            } catch (BusinessRuleViolation $exception) {
                $this->assertSame('invalid-norms', $exception->errorCode());
            }
        }
    }
}
