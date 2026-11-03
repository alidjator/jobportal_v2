<?php

namespace Tests\Unit\Assessment\Domain;

use PHPUnit\Framework\TestCase;
use Src\Assessment\Domain\MostLeast;
use Src\Assessment\Domain\Norm;
use Src\Assessment\Domain\NormCandidate;

final class MostLeastTest extends TestCase
{
    private function norm(string $scale, int $min, int $max, float $standard, string $band): Norm
    {
        return new Norm($scale, $min, $max, $standard, $band, null);
    }

    /** @return list<Norm> every graph: a count of 0 to 1 is 40 (low), from 2 is 60 (high); the change is 40 below 0 and 60 from 0 */
    private function norms(string $high = 'High'): array
    {
        $norms = [];

        foreach (['d', 'i', 's', 'c'] as $dimension) {
            foreach (['most', 'least'] as $graph) {
                $norms[] = $this->norm("{$dimension}_{$graph}", 0, 1, 40, 'Low');
                $norms[] = $this->norm("{$dimension}_{$graph}", 2, 10, 60, $high);
            }

            $norms[] = $this->norm("{$dimension}_change", -10, -1, 40, 'Low');
            $norms[] = $this->norm("{$dimension}_change", 0, 10, 60, $high);
        }

        return $norms;
    }

    public function test_the_scales_of_a_dimension_are_its_three_graphs_sorted(): void
    {
        $this->assertSame(['c_change', 'c_least', 'c_most', 'd_change', 'd_least', 'd_most'], MostLeast::derivedKeys(['d', 'c']));
        $this->assertSame([], MostLeast::derivedKeys([]));
    }

    public function test_the_change_is_most_minus_least_for_every_dimension_with_a_count(): void
    {
        $scales = MostLeast::withChanges(['d_most' => 3.0, 'd_least' => 1.0, 'c_most' => 0.0, 'c_least' => 2.0]);

        $this->assertEquals(2.0, $scales['d_change']);
        $this->assertEquals(-2.0, $scales['c_change']);
        $this->assertSame(['c', 'd'], MostLeast::dimensionsOfScales(array_keys($scales)));
    }

    public function test_the_high_mark_is_the_lowest_standard_score_of_a_band_called_high_else_55(): void
    {
        $this->assertSame(60.0, MostLeast::highMark($this->norms('High')));
        $this->assertSame(60.0, MostLeast::highMark($this->norms('  TINGGI ')));
        $this->assertSame(55.0, MostLeast::highMark($this->norms('Mid')));
        $this->assertSame(55.0, MostLeast::highMark([]));
        $this->assertSame(50.0, MostLeast::highMark([...$this->norms(), $this->norm('d_most', 11, 20, 50, 'high')]));
    }

    public function test_the_profile_lists_the_high_dimensions_highest_first_as_letters_or_a_dash(): void
    {
        $scores = MostLeast::withChanges(['d_most' => 2.0, 'i_most' => 1.0, 's_most' => 0.0, 'c_most' => 0.0, 'd_least' => 0.0, 'i_least' => 0.0, 's_least' => 1.0, 'c_least' => 2.0]);
        $candidate = new NormCandidate;

        $this->assertSame('D', MostLeast::profile($scores, $this->norms(), $candidate, 'most'));
        $this->assertSame('C', MostLeast::profile($scores, $this->norms(), $candidate, 'least'));
        // Change: d 2 and i 1 are 60 (from 0), s -1 and c -2 are 40; the tie keeps the order of the keys.
        $this->assertSame('DI', MostLeast::profile($scores, $this->norms(), $candidate, 'change'));

        $none = MostLeast::withChanges(['d_most' => 0.0, 'i_most' => 0.0, 'd_least' => 0.0, 'i_least' => 0.0]);
        $this->assertSame('-', MostLeast::profile($none, [$this->norm('d_most', 0, 10, 40, 'Low'), $this->norm('i_most', 0, 10, 40, 'Low')], $candidate, 'most'));
    }

    public function test_a_higher_standard_score_comes_first(): void
    {
        $norms = [$this->norm('d_most', 0, 2, 70, 'High'), $this->norm('i_most', 0, 2, 80, 'High')];
        $scores = ['d_most' => 1.0, 'i_most' => 1.0];

        $this->assertSame('ID', MostLeast::profile($scores, $norms, new NormCandidate, 'most'));
    }

    public function test_the_chart_data_has_each_graph_over_the_same_dimensions_with_gaps_where_no_norm_covers(): void
    {
        $chart = MostLeast::chart([
            ['scale' => 'd_most', 'standard' => 60.0], ['scale' => 'd_least', 'standard' => 40.0], ['scale' => 'd_change', 'standard' => 60.0],
            ['scale' => 'i_most', 'standard' => null], ['scale' => 'i_least', 'standard' => 40.0], ['scale' => 'i_change', 'standard' => 40.0],
        ]);

        $this->assertSame(['d', 'i'], $chart['dimensions']);
        $this->assertSame([60.0, null], $chart['graphs']['most']);
        $this->assertSame([40.0, 40.0], $chart['graphs']['least']);
        $this->assertSame([60.0, 40.0], $chart['graphs']['change']);
        $this->assertNull(MostLeast::chart([['scale' => 'extraversion', 'standard' => 50.0]]));
    }
}
