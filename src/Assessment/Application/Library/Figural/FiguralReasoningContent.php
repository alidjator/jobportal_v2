<?php

namespace Src\Assessment\Application\Library\Figural;

/**
 * The content of the figural reasoning test of the system library (`docs/design/phase-8-figural-reasoning-test.md`): four subtests of ten items each, from easy to hard, built on the item mechanisms common to
 * non-verbal reasoning tests (odd one out, series, analogies, 3 by 3 matrices). Every figure is original and drawn by `FigureRenderer`; nothing is copied from a published instrument.
 *
 * Elements, on a 100 by 100 grid, angles in degrees clockwise from straight up:
 * - `['shape', kind, x, y, size, fill, rotation]`: kind `circle`, `triangle`, `square` (size is the side), `diamond`, `pentagon`, `hexagon`, `heptagon`, `octagon`, `star` (size is the outer diameter);
 *   fill `none`, `solid` or `grey`;
 * - `['dot', x, y, radius]`, a solid disc; `['ring', x, y, radius]`, a hollow one;
 * - `['line', x1, y1, x2, y2]`; `['box', x1, y1, x2, y2]`, a rectangle outline;
 * - `['grid', cells]`, a 3 by 3 grid with the cells (0 to 8, row by row) filled;
 * - `['arrow', x, y, length, rotation]`; `['flag', x, y, size, rotation, mirrored, fill]`, a pole with a pennant on its right (left when mirrored), the one figure that tells a turn from a mirror image.
 *
 * @phpstan-import-type Element from FiguralItem
 * @phpstan-import-type Frame from FiguralItem
 */
final class FiguralReasoningContent
{
    public const string TITLE = 'Penalaran Figural';

    public const string DESCRIPTION = 'Tes penalaran figural 40 soal dalam empat subtes: gambar yang berbeda, deret gambar, analogi gambar, dan matriks gambar. Mengukur kemampuan menemukan aturan dari pola gambar, tanpa bergantung pada bahasa atau hitungan.';

    public const string INSTRUCTIONS = 'Tes ini terdiri atas empat subtes, masing-masing dengan petunjuk dan batas waktunya sendiri. Bacalah petunjuk setiap subtes, lalu pilih satu jawaban, A sampai E, untuk setiap soal. Soal makin sulit menuju akhir subtes. Bila ragu, pilihlah jawaban yang paling mungkin.';

    /** @var list<string> */
    public const array CAPABILITIES = ['Penalaran figural', 'Penalaran induktif', 'Pengenalan pola', 'Visualisasi spasial'];

    public const string OPTION_ALT = 'Gambar pilihan %s';

    /** @return list<FiguralSection> */
    public static function sections(): array
    {
        return [
            new FiguralSection(
                'Gambar yang Berbeda',
                'Setiap soal menampilkan lima gambar, A sampai E. Empat gambar mengikuti aturan yang sama, satu gambar tidak. Pilihlah satu gambar yang berbeda dari gambar lain. Contoh: empat lingkaran hitam dan satu persegi hitam; jawabannya persegi itu, karena hanya bentuknya yang lain. Perhatikan bentuk, jumlah, arah, isian, dan letak bagian-bagian gambar.',
                240, 'Pilihlah satu gambar yang berbeda dari gambar lain.', FiguralLayout::OptionsOnly, null,
                [self::sample(), ...self::oddOneOut()],
            ),
            new FiguralSection(
                'Deret Gambar',
                'Setiap soal menampilkan empat gambar yang berubah menurut aturan tertentu dari kiri ke kanan, lalu satu kotak bertanda tanya. Pilihlah gambar A sampai E yang tepat untuk mengisi kotak itu. Contoh: sebuah segitiga makin kecil pada setiap gambar; jawabannya segitiga yang lebih kecil lagi.',
                300, 'Gambar mana yang tepat untuk mengisi kotak bertanda tanya pada deret di atas?', FiguralLayout::Series,
                'Deret empat gambar dan satu kotak bertanda tanya', self::series(),
            ),
            new FiguralSection(
                'Analogi Gambar',
                'Setiap soal menampilkan dua pasang gambar. Gambar kedua berhubungan dengan gambar pertama menurut suatu aturan. Gambar ketiga harus dipasangkan dengan gambar yang berhubungan dengannya menurut aturan yang sama. Pilihlah gambar A sampai E untuk kotak bertanda tanya. Contoh: satu titik menjadi tiga titik, maka satu persegi menjadi tiga persegi.',
                300, 'Gambar mana yang berhubungan dengan gambar ketiga seperti gambar kedua berhubungan dengan gambar pertama?', FiguralLayout::Analogy,
                'Dua pasang gambar; gambar terakhir diganti kotak bertanda tanya', self::analogies(),
            ),
            new FiguralSection(
                'Matriks Gambar',
                'Setiap soal menampilkan sembilan kotak dalam tiga baris dan tiga kolom. Gambar-gambarnya berubah menurut aturan tertentu dari kiri ke kanan dan dari atas ke bawah. Kotak kanan bawah bertanda tanya. Pilihlah gambar A sampai E yang tepat untuk mengisinya. Contoh: setiap baris berisi satu, dua, dan tiga bintang; kotak kanan bawah berisi tiga bintang.',
                420, 'Gambar mana yang tepat untuk mengisi kotak kanan bawah?', FiguralLayout::Matrix,
                'Matriks tiga kali tiga gambar; kotak kanan bawah bertanda tanya', self::matrices(),
            ),
        ];
    }

    /** The worked example of the first subtest, shown in the practice and the catalogue, never scored. */
    private static function sample(): FiguralItem
    {
        $circle = [self::shape('circle', 50, 50, 56, 'solid')];

        return new FiguralItem([], [$circle, $circle, $circle, [self::shape('square', 50, 50, 50, 'solid')], $circle], 3, 'Shape: the only square among circles.', true);
    }

    /** @return list<FiguralItem> */
    private static function oddOneOut(): array
    {
        $polygonWithDots = static fn (string $kind, float $size, int $dots, float $gap, float $cy = 50): array => [self::shape($kind, 50, 50, $size), ...self::many('dot', $dots, 7, 50, $cy, $gap)];
        $nested = static fn (string $outer, float $outerSize, string $inner, float $innerSize): array => [self::shape($outer, 50, 50, $outerSize), self::shape($inner, 50, 50, $innerSize)];
        $clock = static fn (float $first, float $second): array => [self::shape('circle', 50, 50, 84), self::hand($first, 34), self::hand($second, 34), self::dot(50, 50, 3.5)];
        $diagonal = static fn (bool $falling, float $x, float $y): array => [self::box(20, 20, 80, 80), $falling ? self::line(20, 20, 80, 80) : self::line(20, 80, 80, 20), self::dot($x, $y, 5)];

        return [
            self::odd([
                [self::shape('square', 50, 50, 56)], [self::shape('square', 50, 50, 56, 'solid')], [self::shape('circle', 50, 50, 60)],
                [self::shape('square', 50, 50, 56)], [self::shape('square', 50, 50, 56, 'solid')],
            ], 'C', 'Shape: the only circle; the fill varies and is no clue.'),
            self::odd([
                [self::dot(30, 30, 6), self::dot(70, 35, 6), self::dot(40, 70, 6), self::dot(68, 68, 6)],
                [self::dot(20, 50, 6), self::dot(40, 50, 6), self::dot(60, 50, 6), self::dot(80, 50, 6)],
                [self::dot(50, 25, 6), self::dot(25, 50, 6), self::dot(75, 50, 6), self::dot(50, 75, 6)],
                [self::dot(30, 25, 6), self::dot(55, 40, 6), self::dot(35, 62, 6), self::dot(70, 78, 6)],
                [self::dot(28, 30, 6), self::dot(62, 25, 6), self::dot(48, 52, 6), self::dot(30, 74, 6), self::dot(72, 66, 6)],
            ], 'E', 'Count: five dots, the others four; the arrangement varies.'),
            self::odd([[self::flag(0)], [self::flag(90, true)], [self::flag(180)], [self::flag(270)], [self::flag(90)]], 'B', 'Mirror image: the others are the same flag turned; B is flipped.'),
            self::odd([
                $polygonWithDots('triangle', 76, 3, 12, 54), $polygonWithDots('square', 60, 4, 14), $polygonWithDots('hexagon', 72, 6, 12),
                $polygonWithDots('pentagon', 72, 4, 13, 52), $polygonWithDots('diamond', 76, 4, 12),
            ], 'D', 'Relation: the number of dots equals the number of sides, except in D (five sides, four dots).'),
            self::odd([
                $nested('diamond', 76, 'circle', 32), $nested('circle', 72, 'circle', 32), $nested('square', 62, 'square', 28),
                $nested('triangle', 84, 'triangle', 36), $nested('hexagon', 76, 'hexagon', 34),
            ], 'A', 'Relation: the inner shape is the outer shape made small, except in A.'),
            self::odd([$clock(0, 90), $clock(45, 135), $clock(120, 210), $clock(300, 30), $clock(60, 195)], 'E', 'Angle: the hands are a quarter turn apart, except in E (three eighths); the turn of the pair varies.'),
            self::odd([$diagonal(true, 35, 35), $diagonal(false, 65, 35), $diagonal(true, 65, 65), $diagonal(false, 68, 68), $diagonal(false, 35, 65)], 'D', 'Position: the dot lies on the diagonal, except in D.'),
            self::odd([[self::grid([0, 1, 5])], [self::grid([1, 3, 5, 7])], [self::grid([0, 2, 4, 6, 8])], [self::grid([0, 2, 4])], [self::grid([1, 4, 6, 8])]], 'A', 'Symmetry: every pattern is the same on the left and on the right, except A; the count varies.'),
            self::odd([self::pattern('|.o.-.o..'), self::pattern('o/.-.o.|o'), self::pattern('/o-.|.o\\o'), self::pattern('....o/...'), self::pattern('o-o/.\\o|o')], 'C', 'Relation: as many strokes as dots, except in C (four strokes, three dots).'),
            self::odd([self::arrowWithDot(0, -1), self::arrowWithDot(90, 1), self::arrowWithDot(180, -1), self::arrowWithDot(270, -1), self::arrowWithDot(45, -1)], 'B', 'Mirror image: the dot is on the left of the arrow, seen along it, except in B.'),
        ];
    }

    /** @return list<FiguralItem> */
    private static function series(): array
    {
        $arrow = static fn (float $rotation): array => [self::arrow($rotation)];
        $dots = static fn (int $count): array => self::many('dot', $count, 12, 50, 50, 22);
        $square = static fn (float $size, string $fill): array => [self::shape('square', 50, 50, $size, $fill)];
        $corners = static fn (string $solid, string $hollow): array => [self::box(15, 15, 85, 85), self::dot(...self::corner($solid), radius: 7), self::ring(...self::corner($hollow), radius: 7)];
        $clock = static fn (float $long, float $short): array => [self::shape('circle', 50, 50, 84), self::hand($long, 34), self::hand($short, 20), self::dot(50, 50, 3.5)];
        $halves = static fn (int $left, int $right): array => [self::line(50, 12, 50, 88), ...self::many('dot', $left, 9, 27, 50, 15, 2), ...self::many('dot', $right, 9, 73, 50, 15, 2)];
        $turning = static fn (float $rotation, string $fill): array => [self::arrow($rotation, 45, 45, 46), self::shape('square', 80, 80, 14, $fill)];

        return [
            self::pick([$arrow(0), $arrow(45), $arrow(90), $arrow(135)], $arrow(180), [$arrow(225), $arrow(135), $arrow(90), $arrow(0)], 'B', 'The arrow turns an eighth clockwise each step.'),
            self::pick([$dots(1), $dots(2), $dots(3), $dots(4)], $dots(5), [$dots(4), $dots(6), $dots(3), $dots(7)], 'D', 'One dot more each step.'),
            self::pick(
                [[self::shape('triangle', 50, 50, 66)], [self::shape('square', 50, 50, 50)], [self::shape('pentagon', 50, 50, 64)], [self::shape('hexagon', 50, 50, 64)]],
                [self::shape('heptagon', 50, 50, 64)],
                [[self::shape('hexagon', 50, 50, 64)], [self::shape('octagon', 50, 50, 64)], [self::shape('pentagon', 50, 50, 64)], [self::shape('circle', 50, 50, 64)]],
                'A', 'One side more each step.',
            ),
            self::pick(
                [$square(70, 'none'), $square(58, 'solid'), $square(46, 'none'), $square(34, 'solid')], $square(22, 'none'),
                [$square(22, 'solid'), $square(34, 'none'), $square(46, 'none'), [self::shape('circle', 50, 50, 22)]],
                'E', 'The square shrinks each step and the fill alternates.',
            ),
            self::pick(
                [$corners('tl', 'tr'), $corners('tr', 'tl'), $corners('br', 'bl'), $corners('bl', 'br')], $corners('tl', 'tr'),
                [$corners('tr', 'tl'), $corners('tl', 'br'), $corners('bl', 'tr'), $corners('tr', 'br')],
                'C', 'The solid dot moves one corner clockwise, the hollow one one corner anticlockwise.',
            ),
            self::pick(
                [$clock(0, 120), $clock(90, 75), $clock(180, 30), $clock(270, 345)], $clock(0, 300),
                [$clock(0, 345), $clock(90, 300), $clock(300, 0), $clock(0, 30)],
                'A', 'The long hand turns a quarter clockwise, the short one an eighth anticlockwise; the hands never overlap.',
            ),
            self::pick(
                [self::many('circle', 1, 16), self::many('square', 2, 16), self::many('circle', 3, 16), self::many('square', 4, 16)], self::many('circle', 5, 16),
                [self::many('square', 5, 16), self::many('circle', 4, 16), self::many('circle', 6, 16), self::many('triangle', 5, 18)],
                'E', 'One more each step, and circles and squares take turns.',
            ),
            self::pick(
                [[self::grid([0])], [self::grid([5])], [self::grid([6])], [self::grid([1])]], [self::grid([8])],
                [[self::grid([7])], [self::grid([2])], [self::grid([3])], [self::grid([4])]],
                'B', 'The filled cell moves three cells clockwise around the edge each step.',
            ),
            self::pick(
                [$halves(1, 5), $halves(2, 4), $halves(3, 3), $halves(4, 2)], $halves(5, 1),
                [$halves(5, 2), $halves(4, 1), $halves(1, 5), $halves(6, 1)],
                'C', 'The left half gains a dot and the right half loses one each step.',
            ),
            self::pick(
                [$turning(0, 'none'), $turning(135, 'solid'), $turning(270, 'none'), $turning(45, 'solid')], $turning(180, 'none'),
                [$turning(180, 'solid'), $turning(90, 'none'), $turning(225, 'none'), $turning(0, 'none')],
                'D', 'The arrow turns three eighths clockwise each step, and the small square alternates between empty and filled.',
            ),
        ];
    }

    /** @return list<FiguralItem> */
    private static function analogies(): array
    {
        $nested = static fn (string $outer, float $outerSize, string $inner, float $innerSize): array => [self::shape($outer, 50, 50, $outerSize), self::shape($inner, 50, 50, $innerSize)];
        $polygonWithDots = static fn (string $kind, int $dots): array => [self::shape($kind, 50, 50, $kind === 'square' ? 58 : 74), ...self::many('dot', $dots, 7, 50, 52, 12)];
        $row = static fn (array $kinds): array => array_values(array_filter(array_map(
            static fn (?string $kind, int $index): ?array => $kind === null ? null : self::shape($kind, 22 + 28 * $index, 50, 20), $kinds, array_keys($kinds),
        )));

        return [
            self::pick(
                [[self::shape('circle', 50, 50, 70)], [self::shape('circle', 50, 50, 34)], [self::shape('square', 50, 50, 62)]], [self::shape('square', 50, 50, 30)],
                [[self::shape('square', 50, 50, 62)], [self::shape('circle', 50, 50, 30)], [self::shape('triangle', 50, 50, 36)], [self::shape('square', 50, 50, 46)]],
                'C', 'Large becomes small.',
            ),
            self::pick(
                [[self::shape('triangle', 50, 50, 70)], [self::shape('triangle', 50, 50, 70, 'solid')], [self::shape('hexagon', 50, 50, 66)]], [self::shape('hexagon', 50, 50, 66, 'solid')],
                [[self::shape('hexagon', 50, 50, 66)], [self::shape('pentagon', 50, 50, 66, 'solid')], [self::shape('triangle', 50, 50, 70, 'solid')], [self::shape('hexagon', 50, 50, 66, 'grey')]],
                'A', 'Empty becomes filled.',
            ),
            self::pick(
                [[self::arrow(0)], [self::arrow(90)], [self::flag(0)]], [self::flag(90)],
                [[self::flag(270)], [self::flag(90, true)], [self::flag(180)], [self::flag(0)]],
                'E', 'A quarter turn clockwise.',
            ),
            self::pick(
                [self::many('dot', 2, 12), self::many('dot', 4, 12), self::many('square', 3, 16)], self::many('square', 6, 16),
                [self::many('square', 5, 16), self::many('circle', 6, 16), self::many('square', 3, 16), self::many('square', 9, 16)],
                'B', 'The count doubles.',
            ),
            self::pick(
                [$nested('square', 62, 'circle', 30), $nested('circle', 76, 'square', 30), $nested('diamond', 76, 'triangle', 34)], $nested('triangle', 84, 'diamond', 30),
                [$nested('diamond', 76, 'triangle', 34), $nested('circle', 76, 'diamond', 30), $nested('triangle', 84, 'square', 26), $nested('triangle', 84, 'triangle', 36)],
                'D', 'The inner and the outer shape swap places.',
            ),
            self::pick(
                [[self::flag(0)], [self::flag(0, true)], [self::arrow(45, 54, 56, 44), self::dot(24, 24, 6)]], [self::arrow(315, 46, 56, 44), self::dot(76, 24, 6)],
                [[self::arrow(315, 46, 56, 44), self::dot(24, 24, 6)], [self::arrow(135, 46, 56, 44), self::dot(76, 24, 6)], [self::arrow(45, 54, 56, 44), self::dot(76, 24, 6)], [self::arrow(225, 46, 44, 44), self::dot(76, 76, 6)]],
                'D', 'A mirror image left to right (not a turn).',
            ),
            self::pick(
                [$polygonWithDots('triangle', 1), $polygonWithDots('square', 2), $polygonWithDots('pentagon', 3)], $polygonWithDots('hexagon', 4),
                [$polygonWithDots('hexagon', 3), $polygonWithDots('heptagon', 4), $polygonWithDots('pentagon', 4), $polygonWithDots('hexagon', 5)],
                'B', 'One side more and one dot more.',
            ),
            self::pick(
                [$row(['circle', 'square', 'triangle']), $row(['circle', null, 'triangle']), $row(['diamond', 'star', 'hexagon'])], $row(['diamond', null, 'hexagon']),
                [$row(['diamond', 'star', null]), $row([null, 'star', 'hexagon']), $row(['diamond', 'star', 'hexagon']), $row(['hexagon', null, 'diamond'])],
                'A', 'The middle figure is taken away; the others keep their places.',
            ),
            self::pick(
                [[self::shape('triangle', 50, 50, 70, 'solid')], [self::shape('triangle', 50, 50, 70, 'none', 180)], [self::flag(90)]], [self::flag(270, false, 'none')],
                [[self::flag(270)], [self::flag(90, false, 'none')], [self::flag(270, true, 'none')], [self::flag(180, false, 'none')]],
                'E', 'A half turn, and filled becomes empty.',
            ),
            self::pick(
                [[self::grid([0, 1, 5])], [self::grid([1, 2, 3])], [self::grid([0, 3, 7])]], [self::grid([2, 5, 7])],
                [[self::grid([0, 3, 7])], [self::grid([2, 5, 8])], [self::grid([6, 3, 1])], [self::grid([2, 4, 7])]],
                'C', 'A mirror image left to right of the grid.',
            ),
        ];
    }

    /** @return list<FiguralItem> */
    private static function matrices(): array
    {
        $strokes = static fn (string ...$names): array => [self::box(20, 20, 80, 80), ...array_map(static fn (string $name): array => match ($name) {
            'v' => self::line(50, 20, 50, 80),
            'h' => self::line(20, 50, 80, 50),
            'd1' => self::line(20, 20, 80, 80),
            default => self::line(20, 80, 80, 20),
        }, $names)];
        $dots = static fn (string ...$corners): array => [self::box(15, 15, 85, 85), ...array_map(static fn (string $corner): array => self::dot(...self::corner($corner), radius: 6), $corners)];
        $pair = static fn (string $solid, string $hollow): array => [self::box(15, 15, 85, 85), self::dot(...self::corner($solid), radius: 7), self::ring(...self::corner($hollow), radius: 7)];
        $count = static fn (int $n): array => self::many('dot', $n, 11, 50, 50, 20);

        return [
            self::pick(
                [
                    [self::shape('circle', 50, 50, 30)], [self::shape('circle', 50, 50, 48)], [self::shape('circle', 50, 50, 66)],
                    [self::shape('square', 50, 50, 30)], [self::shape('square', 50, 50, 48)], [self::shape('square', 50, 50, 66)],
                    [self::shape('triangle', 50, 50, 30)], [self::shape('triangle', 50, 50, 48)],
                ],
                [self::shape('triangle', 50, 50, 66)],
                [[self::shape('triangle', 50, 50, 48)], [self::shape('square', 50, 50, 66)], [self::shape('triangle', 50, 50, 30)], [self::shape('circle', 50, 50, 66)]],
                'E', 'Each row keeps its shape; the size grows from left to right.',
            ),
            self::pick(
                [
                    self::many('circle', 1, 18), self::many('circle', 2, 18), self::many('circle', 3, 18),
                    self::many('square', 1, 18), self::many('square', 2, 18), self::many('square', 3, 18),
                    self::many('diamond', 1, 20), self::many('diamond', 2, 20),
                ],
                self::many('diamond', 3, 20),
                [self::many('diamond', 2, 20), self::many('square', 3, 18), self::many('diamond', 4, 20), self::many('circle', 3, 18)],
                'C', 'Each row keeps its shape; one, two, three from left to right.',
            ),
            self::pick(
                [
                    [self::shape('circle', 50, 50, 56)], [self::shape('square', 50, 50, 50)], [self::shape('triangle', 50, 50, 62)],
                    [self::shape('square', 50, 50, 50, 'solid')], [self::shape('triangle', 50, 50, 62, 'solid')], [self::shape('circle', 50, 50, 56, 'solid')],
                    [self::shape('triangle', 50, 50, 62, 'grey')], [self::shape('circle', 50, 50, 56, 'grey')],
                ],
                [self::shape('square', 50, 50, 50, 'grey')],
                [[self::shape('square', 50, 50, 50, 'solid')], [self::shape('circle', 50, 50, 56, 'grey')], [self::shape('square', 50, 50, 50)], [self::shape('triangle', 50, 50, 62, 'grey')]],
                'A', 'Every row holds a circle, a square and a triangle once; each row has its own fill.',
            ),
            self::pick(
                [[self::arrow(0)], [self::arrow(90)], [self::arrow(180)], [self::arrow(45)], [self::arrow(135)], [self::arrow(225)], [self::arrow(90)], [self::arrow(180)]],
                [self::arrow(270)],
                [[self::arrow(90)], [self::arrow(0)], [self::arrow(180)], [self::arrow(315)]],
                'D', 'The arrow turns a quarter clockwise from column to column; each row starts an eighth further.',
            ),
            self::pick(
                [$strokes('v'), $strokes('h'), $strokes('v', 'h'), $strokes('d1'), $strokes('d2'), $strokes('d1', 'd2'), $strokes('v'), $strokes('d1')],
                $strokes('v', 'd1'),
                [$strokes('v', 'd2'), $strokes('h', 'd1'), $strokes('v'), $strokes('v', 'd1', 'd2')],
                'B', 'The third figure of a row is the first two laid over each other.',
            ),
            self::pick(
                [$dots('tl', 'tr'), $dots('tr', 'br'), $dots('tl', 'br'), $dots('tl', 'c'), $dots('c', 'bl'), $dots('tl', 'bl'), $dots('tr', 'bl', 'c'), $dots('bl', 'br')],
                $dots('tr', 'c', 'br'),
                [$dots('tr', 'bl', 'c', 'br'), $dots('bl'), $dots('tr', 'br'), $dots('tl', 'c', 'br')],
                'B', 'The third figure of a row keeps the dots that are in only one of the first two; the dots in both disappear.',
            ),
            self::pick(
                [
                    self::many('circle', 1, 18), self::many('square', 2, 18), self::many('triangle', 3, 20),
                    self::many('triangle', 2, 20), self::many('circle', 3, 18), self::many('square', 1, 18),
                    self::many('square', 3, 18), self::many('triangle', 1, 20),
                ],
                self::many('circle', 2, 18),
                [self::many('circle', 3, 18), self::many('square', 2, 18), self::many('circle', 1, 18), self::many('triangle', 2, 20)],
                'D', 'Every row and every column holds each shape once and each count (one, two, three) once.',
            ),
            self::pick(
                [$pair('tl', 'br'), $pair('tr', 'bl'), $pair('br', 'tl'), $pair('tr', 'bl'), $pair('br', 'tl'), $pair('bl', 'tr'), $pair('br', 'tl'), $pair('bl', 'tr')],
                $pair('tl', 'br'),
                [$pair('br', 'tl'), $pair('tl', 'tr'), $pair('tr', 'bl'), $pair('bl', 'tr')],
                'E', 'The solid dot moves one corner clockwise from column to column; the hollow one is always in the opposite corner.',
            ),
            self::pick(
                [$count(1), $count(2), $count(3), $count(2), $count(2), $count(4), $count(2), $count(3)], $count(5),
                [$count(6), $count(4), $count(1), $count(3)],
                'A', 'The third count of a row is the sum of the first two.',
            ),
            self::pick(
                [
                    self::many('circle', 1, 18), self::many('square', 2, 18, fill: 'solid'), self::many('triangle', 3, 20, fill: 'grey'),
                    self::many('square', 3, 18, fill: 'grey'), self::many('triangle', 1, 20), self::many('circle', 2, 18, fill: 'solid'),
                    self::many('triangle', 2, 20, fill: 'solid'), self::many('circle', 3, 18, fill: 'grey'),
                ],
                self::many('square', 1, 18),
                [self::many('square', 1, 18, fill: 'solid'), self::many('square', 2, 18), self::many('circle', 1, 18), self::many('square', 3, 18, fill: 'grey')],
                'C', 'Shape, count and fill each appear once in every row and every column.',
            ),
        ];
    }

    // ----- builders -----

    /** @param  list<Frame>  $frames  A to E */
    private static function odd(array $frames, string $key, string $rule): FiguralItem
    {
        return new FiguralItem([], $frames, self::position($key), $rule);
    }

    /**
     * @param  list<Frame>  $stem
     * @param  Frame  $answer
     * @param  list<Frame>  $distractors  four, placed around the answer in this order
     */
    private static function pick(array $stem, array $answer, array $distractors, string $key, string $rule): FiguralItem
    {
        $position = self::position($key);
        array_splice($distractors, $position, 0, [$answer]);

        return new FiguralItem($stem, $distractors, $position, $rule);
    }

    private static function position(string $key): int
    {
        return (int) strpos('ABCDE', $key);
    }

    // ----- elements -----

    /** @return Element */
    private static function shape(string $kind, float $x = 50, float $y = 50, float $size = 60, string $fill = 'none', float $rotation = 0): array
    {
        return ['shape', $kind, $x, $y, $size, $fill, $rotation];
    }

    /** @return Element */
    private static function dot(float $x, float $y, float $radius = 5): array
    {
        return ['dot', $x, $y, $radius];
    }

    /** @return Element */
    private static function ring(float $x, float $y, float $radius = 6): array
    {
        return ['ring', $x, $y, $radius];
    }

    /** @return Element */
    private static function line(float $x1, float $y1, float $x2, float $y2): array
    {
        return ['line', $x1, $y1, $x2, $y2];
    }

    /** @return Element */
    private static function box(float $x1, float $y1, float $x2, float $y2): array
    {
        return ['box', $x1, $y1, $x2, $y2];
    }

    /**
     * @param  list<int>  $cells
     * @return Element
     */
    private static function grid(array $cells): array
    {
        return ['grid', $cells];
    }

    /** @return Element */
    private static function arrow(float $rotation, float $x = 50, float $y = 50, float $length = 56): array
    {
        return ['arrow', $x, $y, $length, $rotation];
    }

    /** @return Element */
    private static function flag(float $rotation, bool $mirrored = false, string $fill = 'solid'): array
    {
        return ['flag', 50, 50, 60, $rotation, $mirrored, $fill];
    }

    /** A clock hand from the centre. @return Element */
    private static function hand(float $angle, float $length): array
    {
        [$x, $y] = self::turn(0, -$length, $angle);

        return self::line(50, 50, 50 + $x, 50 + $y);
    }

    /** @return Frame an arrow with a dot beside it: on its left (seen along the arrow) for -1, on its right for 1 */
    private static function arrowWithDot(float $rotation, int $side): array
    {
        [$x, $y] = self::turn(24 * $side, 0, $rotation);

        return [self::arrow($rotation, 50, 50, 50), self::dot(50 + $x, 50 + $y, 6)];
    }

    /**
     * Nine slots, row by row: `o` a dot, `|`, `-`, `/` and `\` a short stroke, `.` nothing.
     *
     * @return Frame
     */
    private static function pattern(string $slots): array
    {
        $frame = [];

        foreach (str_split($slots) as $index => $slot) {
            $x = 25 + 25 * ($index % 3);
            $y = 25 + 25 * intdiv($index, 3);
            $frame = [...$frame, ...match ($slot) {
                'o' => [self::dot($x, $y, 5)],
                '|' => [self::line($x, $y - 9, $x, $y + 9)],
                '-' => [self::line($x - 9, $y, $x + 9, $y)],
                '/' => [self::line($x - 7, $y + 7, $x + 7, $y - 7)],
                '\\' => [self::line($x - 7, $y - 7, $x + 7, $y + 7)],
                default => [],
            }];
        }

        return $frame;
    }

    /**
     * `count` figures of one kind (`dot` or a shape kind), in rows centred on the point; five make the pattern of a die unless the columns are given.
     *
     * @return Frame
     */
    private static function many(string $kind, int $count, float $size, float $cx = 50, float $cy = 50, float $gap = 24, ?int $columns = null, string $fill = 'none'): array
    {
        return array_map(
            static fn (array $place): array => $kind === 'dot' ? self::dot($place[0], $place[1], $size / 2) : self::shape($kind, $place[0], $place[1], $size, $fill),
            self::places($count, $cx, $cy, $gap, $columns),
        );
    }

    /** @return list<array{float, float}> */
    private static function places(int $count, float $cx, float $cy, float $gap, ?int $columns): array
    {
        if ($count === 5 && $columns === null) {
            return [[$cx - $gap, $cy - $gap], [$cx + $gap, $cy - $gap], [$cx, $cy], [$cx - $gap, $cy + $gap], [$cx + $gap, $cy + $gap]];
        }

        $columns ??= $count <= 3 ? max(1, $count) : ($count === 4 ? 2 : 3);
        $rows = (int) ceil($count / $columns);
        $places = [];

        for ($row = 0; $row < $rows; $row++) {
            $inRow = min($columns, $count - $row * $columns);

            for ($column = 0; $column < $inRow; $column++) {
                $places[] = [$cx - ($inRow - 1) * $gap / 2 + $column * $gap, $cy - ($rows - 1) * $gap / 2 + $row * $gap];
            }
        }

        return $places;
    }

    /** @return array{x: float, y: float} the centre of a corner (`tl`, `tr`, `br`, `bl`) or of the middle (`c`) of a frame with a box from 15 to 85 */
    private static function corner(string $name): array
    {
        return match ($name) {
            'tl' => ['x' => 32, 'y' => 32],
            'tr' => ['x' => 68, 'y' => 32],
            'br' => ['x' => 68, 'y' => 68],
            'bl' => ['x' => 32, 'y' => 68],
            default => ['x' => 50, 'y' => 50],
        };
    }

    /** @return array{float, float} the vector turned clockwise by the angle (screen coordinates, y down) */
    private static function turn(float $x, float $y, float $degrees): array
    {
        $radians = deg2rad($degrees);

        return [round($x * cos($radians) - $y * sin($radians), 2), round($x * sin($radians) + $y * cos($radians), 2)];
    }
}
