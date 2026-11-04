<?php

namespace Src\Assessment\Infrastructure\Figures;

use GdImage;

/**
 * A white picture the figures are drawn on, in pixels of the final picture. It is drawn at three times the size and shrunk at the end, so the lines come out smooth (GD draws thick lines without antialiasing).
 * The elements are described in `FiguralReasoningContent`.
 */
final class GdFigureCanvas
{
    private const int SUPERSAMPLE = 3;

    /** The stroke in final pixels. */
    private const float STROKE = 2.6;

    private GdImage $image;

    private int $ink;

    private int $grey;

    private int $paper;

    private int $edge;

    private int $muted;

    public function __construct(private readonly int $width, private readonly int $height)
    {
        $image = imagecreatetruecolor($width * self::SUPERSAMPLE, $height * self::SUPERSAMPLE);
        $this->image = $image;
        $this->ink = (int) imagecolorallocate($image, 25, 25, 25);
        $this->grey = (int) imagecolorallocate($image, 160, 160, 160);
        $this->paper = (int) imagecolorallocate($image, 255, 255, 255);
        $this->edge = (int) imagecolorallocate($image, 200, 200, 200);
        $this->muted = (int) imagecolorallocate($image, 130, 130, 130);
        imagefill($image, 0, 0, $this->paper);
    }

    /** @param  list<list<mixed>>  $elements */
    public function figure(float $left, float $top, float $cell, array $elements): void
    {
        $this->cellEdge($left, $top, $cell);
        $unit = $cell / 100;

        foreach ($elements as $element) {
            $this->element(static fn (float $x, float $y): array => [$left + $x * $unit, $top + $y * $unit], $unit, $element);
        }
    }

    /** The cell of the answer: an edge and a question mark. */
    public function question(float $left, float $top, float $cell): void
    {
        $this->cellEdge($left, $top, $cell);
        $unit = $cell / 100;
        $points = [];

        for ($degrees = 180; $degrees <= 450; $degrees += 10) {
            $points[] = [$left + (50 + 13 * cos(deg2rad($degrees))) * $unit, $top + (40 + 13 * sin(deg2rad($degrees))) * $unit];
        }

        $points[] = [$left + 50 * $unit, $top + 62 * $unit];
        $this->polyline($points, $this->muted, self::STROKE * 1.6, false);
        $this->disc($left + 50 * $unit, $top + 74 * $unit, 3.6 * $unit, $this->muted);
    }

    /** A short arrow pointing right, centred on the point (between the two figures of a pair). */
    public function link(float $cx, float $cy, float $length): void
    {
        $half = $length / 2;
        $this->polyline([[$cx - $half, $cy], [$cx + $half - 8, $cy]], $this->muted, self::STROKE, false);
        $this->fillPolygon([[$cx + $half, $cy], [$cx + $half - 11, $cy - 6], [$cx + $half - 11, $cy + 6]], $this->muted);
    }

    /** A thin vertical line (between the two pairs). */
    public function divider(float $x, float $top, float $bottom): void
    {
        $this->polyline([[$x, $top], [$x, $bottom]], $this->edge, self::STROKE * 0.6, false);
    }

    public function png(): string
    {
        $final = imagecreatetruecolor($this->width, $this->height);
        imagecopyresampled($final, $this->image, 0, 0, 0, 0, $this->width, $this->height, $this->width * self::SUPERSAMPLE, $this->height * self::SUPERSAMPLE);

        ob_start();
        imagepng($final);

        return (string) ob_get_clean();
    }

    // ----- elements -----

    /**
     * @param  callable(float, float): array{float, float}  $at  grid point to pixel
     * @param  list<mixed>  $element
     */
    private function element(callable $at, float $unit, array $element): void
    {
        $f = static fn (int $index): float => (float) $element[$index];

        match ($element[0]) {
            'shape' => $this->shape($at, $unit, (string) $element[1], $f(2), $f(3), $f(4), (string) $element[5], $f(6)),
            'dot' => $this->disc(...[...$at($f(1), $f(2)), $f(3) * $unit, $this->ink]),
            'ring' => $this->ring($at($f(1), $f(2)), $f(3) * $unit),
            'line' => $this->polyline([$at($f(1), $f(2)), $at($f(3), $f(4))], $this->ink, self::STROKE, false),
            'box' => $this->polyline([$at($f(1), $f(2)), $at($f(3), $f(2)), $at($f(3), $f(4)), $at($f(1), $f(4))], $this->ink, self::STROKE, true),
            'grid' => $this->grid($at, array_map('intval', (array) $element[1])),
            'arrow' => $this->arrow($at, $f(1), $f(2), $f(3), $f(4)),
            'flag' => $this->flag($at, $f(1), $f(2), $f(3), $f(4), (bool) $element[5], (string) $element[6]),
            default => null,
        };
    }

    /** @param  callable(float, float): array{float, float}  $at */
    private function shape(callable $at, float $unit, string $kind, float $x, float $y, float $size, string $fill, float $rotation): void
    {
        [$sides, $start, $radius] = match ($kind) {
            'circle' => [96, 0.0, $size / 2],
            'triangle' => [3, 0.0, $size / 2],
            'square' => [4, 45.0, $size / M_SQRT2],
            'diamond' => [4, 0.0, $size / 2],
            'pentagon' => [5, 0.0, $size / 2],
            'hexagon' => [6, 0.0, $size / 2],
            'heptagon' => [7, 0.0, $size / 2],
            'octagon' => [8, 22.5, $size / 2],
            default => [0, 0.0, $size / 2],
        };

        if ($sides === 0) {
            $this->star($at, $x, $y, $radius, $fill, $rotation);

            return;
        }

        $outer = $this->regular($at, $x, $y, $radius, $sides, $start + $rotation);
        $this->fillPolygon($outer, $this->ink);

        if ($fill === 'solid') {
            return;
        }

        $stroke = self::STROKE / $unit;
        $inner = $this->regular($at, $x, $y, $radius - $stroke / cos(M_PI / $sides), $sides, $start + $rotation);
        $this->fillPolygon($inner, $fill === 'grey' ? $this->grey : $this->paper);
    }

    /** @param  callable(float, float): array{float, float}  $at */
    private function star(callable $at, float $x, float $y, float $radius, string $fill, float $rotation): void
    {
        $points = [];

        for ($index = 0; $index < 10; $index++) {
            $r = $index % 2 === 0 ? $radius : $radius * 0.42;
            $angle = deg2rad(-90 + $rotation + 36 * $index);
            $points[] = $at($x + $r * cos($angle), $y + $r * sin($angle));
        }

        if ($fill !== 'none') {
            $this->fillPolygon($points, $fill === 'solid' ? $this->ink : $this->grey);
        }

        $this->polyline($points, $this->ink, self::STROKE, true);
    }

    /**
     * @param  callable(float, float): array{float, float}  $at
     * @param  list<int>  $cells
     */
    private function grid(callable $at, array $cells): void
    {
        foreach ($cells as $cell) {
            $x = 20 + 20 * ($cell % 3);
            $y = 20 + 20 * intdiv($cell, 3);
            $this->fillPolygon([$at($x, $y), $at($x + 20, $y), $at($x + 20, $y + 20), $at($x, $y + 20)], $this->ink);
        }

        for ($line = 0; $line <= 3; $line++) {
            $offset = 20 + 20 * $line;
            $this->polyline([$at($offset, 20), $at($offset, 80)], $this->ink, self::STROKE, false);
            $this->polyline([$at(20, $offset), $at(80, $offset)], $this->ink, self::STROKE, false);
        }
    }

    /** @param  callable(float, float): array{float, float}  $at */
    private function arrow(callable $at, float $x, float $y, float $length, float $rotation): void
    {
        $half = $length / 2;
        $head = $length * 0.32;
        $place = fn (float $dx, float $dy): array => $this->placed($at, $x, $y, $dx, $dy, $rotation);

        $this->polyline([$place(0, $half), $place(0, -$half + $head * 0.8)], $this->ink, self::STROKE, false);
        $this->fillPolygon([$place(0, -$half), $place(-$length * 0.19, -$half + $head), $place($length * 0.19, -$half + $head)], $this->ink);
    }

    /** @param  callable(float, float): array{float, float}  $at */
    private function flag(callable $at, float $x, float $y, float $size, float $rotation, bool $mirrored, string $fill): void
    {
        $half = $size / 2;
        $side = $mirrored ? -1 : 1;
        $place = fn (float $dx, float $dy): array => $this->placed($at, $x, $y, $dx * $side, $dy, $rotation);
        $pennant = [$place(0, -$half), $place($size * 0.55, -$half + $size * 0.2), $place(0, -$half + $size * 0.4)];

        if ($fill !== 'none') {
            $this->fillPolygon($pennant, $fill === 'grey' ? $this->grey : $this->ink);
        }

        $this->polyline($pennant, $this->ink, self::STROKE, true);
        $this->polyline([$place(0, -$half), $place(0, $half)], $this->ink, self::STROKE, false);
    }

    /**
     * @param  callable(float, float): array{float, float}  $at
     * @return array{float, float}
     */
    private function placed(callable $at, float $x, float $y, float $dx, float $dy, float $rotation): array
    {
        $radians = deg2rad($rotation);

        return $at($x + $dx * cos($radians) - $dy * sin($radians), $y + $dx * sin($radians) + $dy * cos($radians));
    }

    /**
     * @param  callable(float, float): array{float, float}  $at
     * @return list<array{float, float}>
     */
    private function regular(callable $at, float $x, float $y, float $radius, int $sides, float $start): array
    {
        $points = [];

        for ($index = 0; $index < $sides; $index++) {
            $angle = deg2rad(-90 + $start + 360 / $sides * $index);
            $points[] = $at($x + $radius * cos($angle), $y + $radius * sin($angle));
        }

        return $points;
    }

    /** @param  array{float, float}  $centre */
    private function ring(array $centre, float $radius): void
    {
        $this->disc($centre[0], $centre[1], $radius, $this->ink);
        $this->disc($centre[0], $centre[1], $radius - self::STROKE, $this->paper);
    }

    // ----- primitives, in final pixels -----

    private function cellEdge(float $left, float $top, float $cell): void
    {
        $this->polyline([[$left, $top], [$left + $cell, $top], [$left + $cell, $top + $cell], [$left, $top + $cell]], $this->edge, self::STROKE * 0.6, true);
    }

    private function disc(float $x, float $y, float $radius, int $colour): void
    {
        $diameter = (int) round(2 * $radius * self::SUPERSAMPLE);
        imagefilledellipse($this->image, (int) round($x * self::SUPERSAMPLE), (int) round($y * self::SUPERSAMPLE), $diameter, $diameter, $colour);
    }

    /** @param  list<array{float, float}>  $points */
    private function fillPolygon(array $points, int $colour): void
    {
        $flat = [];

        foreach ($points as [$x, $y]) {
            $flat[] = (int) round($x * self::SUPERSAMPLE);
            $flat[] = (int) round($y * self::SUPERSAMPLE);
        }

        imagefilledpolygon($this->image, $flat, $colour);
    }

    /**
     * Thick segments with round joints and ends.
     *
     * @param  list<array{float, float}>  $points
     */
    private function polyline(array $points, int $colour, float $stroke, bool $closed): void
    {
        if ($closed) {
            $points[] = $points[0];
        }

        imagesetthickness($this->image, (int) round($stroke * self::SUPERSAMPLE));

        for ($index = 1, $count = count($points); $index < $count; $index++) {
            [$x1, $y1] = $points[$index - 1];
            [$x2, $y2] = $points[$index];
            imageline($this->image, (int) round($x1 * self::SUPERSAMPLE), (int) round($y1 * self::SUPERSAMPLE), (int) round($x2 * self::SUPERSAMPLE), (int) round($y2 * self::SUPERSAMPLE), $colour);
        }

        imagesetthickness($this->image, 1);

        foreach ($points as [$x, $y]) {
            $this->disc($x, $y, $stroke / 2, $colour);
        }
    }
}
