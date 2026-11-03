<?php

namespace Src\Assessment\Application\Reports;

/**
 * The line chart of a DISC report as an inline SVG that dompdf draws (`docs/design/phase-8-disc-profile.md` D5): one line per graph (most, least, change) over the dimensions, at the standard score of the
 * dimension in that graph. A dimension without a standard score leaves a gap. The axis runs over the scores with a margin of ten and at least forty points; the lines differ by dash and shape as well as by
 * grey, so the chart reads in a black and white print.
 */
final class ProfileChartSvg
{
    private const int WIDTH = 480;

    private const int HEIGHT = 230;

    private const int LEFT = 44;

    private const int RIGHT = 16;

    private const int TOP = 16;

    private const int BOTTOM = 56;

    /** Stroke grey, dash and point shape of each graph. */
    private const array STYLES = [
        'most' => ['#111111', '', 'circle'],
        'least' => ['#666666', '6 3', 'square'],
        'change' => ['#999999', '2 3', 'diamond'],
    ];

    /**
     * @param  array{dimensions: list<string>, graphs: array<string, list<float|null>>}  $chart
     * @param  array<string, string>  $labels  the names of the graphs, by `most`, `least` and `change`
     */
    public static function render(array $chart, array $labels): string
    {
        $values = array_filter(array_merge(...array_values($chart['graphs'])), static fn (?float $value): bool => $value !== null);
        [$low, $high] = self::domain($values === [] ? [50.0] : array_values($values));

        $count = count($chart['dimensions']);
        $plotWidth = self::WIDTH - self::LEFT - self::RIGHT;
        $plotHeight = self::HEIGHT - self::TOP - self::BOTTOM;
        $x = static fn (int $index): float => self::LEFT + ($count === 1 ? $plotWidth / 2 : $plotWidth * $index / ($count - 1));
        $y = static fn (float $value): float => self::TOP + $plotHeight * (1 - ($value - $low) / ($high - $low));

        $svg = sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" font-family="DejaVu Sans, sans-serif" font-size="8">', self::WIDTH, self::HEIGHT, self::WIDTH, self::HEIGHT);

        // The grid: a line and a figure every ten points.
        for ($tick = (int) ceil($low / 10) * 10; $tick <= $high; $tick += 10) {
            $svg .= sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#dddddd" stroke-width="0.5"/>', self::LEFT, $y($tick), self::WIDTH - self::RIGHT, $y($tick));
            $svg .= sprintf('<text x="%d" y="%.1f" text-anchor="end" fill="#555555">%d</text>', self::LEFT - 6, $y($tick) + 3, $tick);
        }

        foreach ($chart['dimensions'] as $index => $dimension) {
            $svg .= sprintf('<line x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke="#dddddd" stroke-width="0.5"/>', $x($index), self::TOP, $x($index), self::TOP + $plotHeight);
            $svg .= sprintf('<text x="%.1f" y="%d" text-anchor="middle" font-size="10" font-weight="bold" fill="#222222">%s</text>', $x($index), self::TOP + $plotHeight + 16, htmlspecialchars(strtoupper($dimension), ENT_XML1));
        }

        $legendX = self::LEFT;

        foreach (self::STYLES as $graph => [$stroke, $dash, $shape]) {
            $points = $chart['graphs'][$graph] ?? [];
            $segment = [];

            foreach ($points as $index => $value) {
                if ($value === null) {
                    $svg .= self::line($segment, $stroke, $dash);
                    $segment = [];

                    continue;
                }

                $segment[] = [$x($index), $y($value)];
            }

            $svg .= self::line($segment, $stroke, $dash);

            foreach ($points as $index => $value) {
                if ($value !== null) {
                    $svg .= self::mark($shape, $x($index), $y($value), $stroke);
                }
            }

            // The legend under the dimensions: the line sample and the name of the graph.
            $legendY = self::HEIGHT - 12;
            $svg .= sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="1.5"%s/>', $legendX, $legendY, $legendX + 22, $legendY, $stroke, $dash === '' ? '' : sprintf(' stroke-dasharray="%s"', $dash));
            $svg .= sprintf('<text x="%d" y="%d" fill="#222222">%s</text>', $legendX + 28, $legendY + 3, htmlspecialchars($labels[$graph] ?? $graph, ENT_XML1));
            $legendX += 28 + 8 * max(8, mb_strlen($labels[$graph] ?? $graph)) / 1.6 + 16;
        }

        return $svg.'</svg>';
    }

    /**
     * The low and high end of the axis: the scores with a margin of ten, rounded to tens, and at least forty points wide.
     *
     * @param  list<float>  $values
     * @return array{0: float, 1: float}
     */
    private static function domain(array $values): array
    {
        $low = floor((min($values) - 5) / 10) * 10;
        $high = ceil((max($values) + 5) / 10) * 10;

        if ($high - $low < 40) {
            $middle = ($low + $high) / 2;
            $low = floor(($middle - 20) / 10) * 10;
            $high = $low + 40;
        }

        return [$low, $high];
    }

    /** @param  list<array{0: float, 1: float}>  $segment */
    private static function line(array $segment, string $stroke, string $dash): string
    {
        if (count($segment) < 2) {
            return '';
        }

        return sprintf(
            '<polyline points="%s" fill="none" stroke="%s" stroke-width="1.5"%s/>',
            implode(' ', array_map(static fn (array $point): string => sprintf('%.1f,%.1f', $point[0], $point[1]), $segment)),
            $stroke,
            $dash === '' ? '' : sprintf(' stroke-dasharray="%s"', $dash),
        );
    }

    private static function mark(string $shape, float $x, float $y, string $stroke): string
    {
        return match ($shape) {
            'square' => sprintf('<rect x="%.1f" y="%.1f" width="5" height="5" fill="%s"/>', $x - 2.5, $y - 2.5, $stroke),
            'diamond' => sprintf('<polygon points="%.1f,%.1f %.1f,%.1f %.1f,%.1f %.1f,%.1f" fill="%s"/>', $x, $y - 3.5, $x + 3.5, $y, $x, $y + 3.5, $x - 3.5, $y, $stroke),
            default => sprintf('<circle cx="%.1f" cy="%.1f" r="2.8" fill="%s"/>', $x, $y, $stroke),
        };
    }
}
