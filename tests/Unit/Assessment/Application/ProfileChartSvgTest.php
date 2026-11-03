<?php

namespace Tests\Unit\Assessment\Application;

use PHPUnit\Framework\TestCase;
use Src\Assessment\Application\Reports\ProfileChartSvg;

final class ProfileChartSvgTest extends TestCase
{
    private const array LABELS = ['most' => 'Paling', 'least' => 'Paling tidak', 'change' => 'Perubahan'];

    public function test_it_draws_a_line_per_graph_over_the_dimensions_with_their_letters_and_the_legend(): void
    {
        $svg = ProfileChartSvg::render([
            'dimensions' => ['d', 'i', 's', 'c'],
            'graphs' => ['most' => [60.0, 55.0, 40.0, 35.0], 'least' => [30.0, 40.0, 50.0, 62.0], 'change' => [58.0, 50.0, 45.0, 30.0]],
        ], self::LABELS);

        $this->assertStringStartsWith('<svg ', $svg);
        $this->assertStringEndsWith('</svg>', $svg);
        $this->assertSame(3, substr_count($svg, '<polyline'));
        foreach (['>D<', '>I<', '>S<', '>C<', '>Paling<', '>Paling tidak<', '>Perubahan<'] as $text) {
            $this->assertStringContainsString($text, $svg);
        }

        // The graphs differ by dash and shape, not only by grey.
        $this->assertStringContainsString('stroke-dasharray="6 3"', $svg);
        $this->assertStringContainsString('stroke-dasharray="2 3"', $svg);
        $this->assertStringContainsString('<circle', $svg);
        $this->assertStringContainsString('<rect', $svg);
        $this->assertStringContainsString('<polygon', $svg);
    }

    public function test_a_missing_score_leaves_a_gap_and_no_mark(): void
    {
        $svg = ProfileChartSvg::render([
            'dimensions' => ['d', 'i', 's'],
            'graphs' => ['most' => [60.0, null, 40.0], 'least' => [30.0, 40.0, 50.0], 'change' => [null, null, null]],
        ], self::LABELS);

        // Most breaks at the gap (two single points, no line), least is one line, change has nothing.
        $this->assertSame(1, substr_count($svg, '<polyline'));
        $this->assertSame(2, substr_count($svg, '<circle'));
        $this->assertSame(3, substr_count($svg, '<rect'));
        $this->assertSame(0, substr_count($svg, '<polygon'));
    }

    public function test_the_axis_follows_the_scores_with_at_least_forty_points(): void
    {
        $narrow = ProfileChartSvg::render(['dimensions' => ['d', 'i'], 'graphs' => ['most' => [50.0, 52.0], 'least' => [50.0, 51.0], 'change' => [50.0, 50.0]]], self::LABELS);
        $wide = ProfileChartSvg::render(['dimensions' => ['d', 'i'], 'graphs' => ['most' => [10.0, 95.0], 'least' => [50.0, 51.0], 'change' => [50.0, 50.0]]], self::LABELS);

        $this->assertGreaterThanOrEqual(5, substr_count($narrow, 'text-anchor="end"'), 'At least forty points: five figures from ten to ten.');
        $this->assertStringContainsString('>100<', $wide);
        $this->assertStringContainsString('>10<', $wide);
    }

    public function test_the_text_is_escaped(): void
    {
        $svg = ProfileChartSvg::render(['dimensions' => ['d', 'i'], 'graphs' => ['most' => [50.0, 52.0], 'least' => [50.0, 51.0], 'change' => [50.0, 50.0]]], ['most' => 'A & B', 'least' => '<x>', 'change' => 'C']);

        $this->assertStringContainsString('A &amp; B', $svg);
        $this->assertStringContainsString('&lt;x&gt;', $svg);
        $this->assertStringNotContainsString('<x>', $svg);
    }
}
