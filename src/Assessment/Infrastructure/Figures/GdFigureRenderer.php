<?php

namespace Src\Assessment\Infrastructure\Figures;

use LogicException;
use Src\Assessment\Application\Library\Figural\FiguralLayout;
use Src\Assessment\Application\Library\Figural\FigureRenderer;

/** `FigureRenderer` with GD: every frame is a 160 pixel cell, so the widest stem (a series of five cells) stays well under the 1600 pixels of an uploaded picture. */
final readonly class GdFigureRenderer implements FigureRenderer
{
    private const int CELL = 160;

    private const int GAP = 16;

    private const int MATRIX_GAP = 8;

    private const int LINK = 56;

    private const int SPLIT = 48;

    public function frame(array $frame): string
    {
        $canvas = new GdFigureCanvas(self::CELL, self::CELL);
        $canvas->figure(0, 0, self::CELL - 1, $frame);

        return $canvas->png();
    }

    public function stem(FiguralLayout $layout, array $frames): string
    {
        return match ($layout) {
            FiguralLayout::Series => $this->series($frames),
            FiguralLayout::Analogy => $this->analogy($frames),
            FiguralLayout::Matrix => $this->matrix($frames),
            FiguralLayout::OptionsOnly => throw new LogicException('An odd-one-out item has no stem picture.'),
        };
    }

    /** @param  list<list<list<mixed>>>  $frames */
    private function series(array $frames): string
    {
        $cells = count($frames) + 1;
        $canvas = new GdFigureCanvas($cells * self::CELL + ($cells + 1) * self::GAP, self::CELL + 2 * self::GAP);

        foreach ($frames as $index => $frame) {
            $canvas->figure(self::GAP + $index * (self::CELL + self::GAP), self::GAP, self::CELL, $frame);
        }

        $canvas->question(self::GAP + count($frames) * (self::CELL + self::GAP), self::GAP, self::CELL);

        return $canvas->png();
    }

    /** @param  list<list<list<mixed>>>  $frames  the first pair and the third figure */
    private function analogy(array $frames): string
    {
        $canvas = new GdFigureCanvas(2 * self::GAP + 4 * self::CELL + 2 * self::LINK + self::SPLIT, self::CELL + 2 * self::GAP);
        $top = self::GAP;
        $middle = $top + self::CELL / 2;
        $second = self::GAP + self::CELL + self::LINK;
        $third = $second + self::CELL + self::SPLIT;
        $fourth = $third + self::CELL + self::LINK;

        $canvas->figure(self::GAP, $top, self::CELL, $frames[0]);
        $canvas->link(self::GAP + self::CELL + self::LINK / 2, $middle, self::LINK - 16);
        $canvas->figure($second, $top, self::CELL, $frames[1]);
        $canvas->divider($second + self::CELL + self::SPLIT / 2, $top + 16, $top + self::CELL - 16);
        $canvas->figure($third, $top, self::CELL, $frames[2]);
        $canvas->link($third + self::CELL + self::LINK / 2, $middle, self::LINK - 16);
        $canvas->question($fourth, $top, self::CELL);

        return $canvas->png();
    }

    /** @param  list<list<list<mixed>>>  $frames  the eight cells before the last, row by row */
    private function matrix(array $frames): string
    {
        $size = 3 * self::CELL + 4 * self::MATRIX_GAP;
        $canvas = new GdFigureCanvas($size, $size);
        $at = static fn (int $index): array => [self::MATRIX_GAP + ($index % 3) * (self::CELL + self::MATRIX_GAP), self::MATRIX_GAP + intdiv($index, 3) * (self::CELL + self::MATRIX_GAP)];

        foreach ($frames as $index => $frame) {
            [$left, $top] = $at($index);
            $canvas->figure($left, $top, self::CELL, $frame);
        }

        [$left, $top] = $at(8);
        $canvas->question($left, $top, self::CELL);

        return $canvas->png();
    }
}
