<?php

namespace Src\DemoData\Application;

use GdImage;
use Src\Assessment\Application\Authoring\AddQuestion;
use Src\Assessment\Application\Authoring\AddSection;
use Src\Assessment\Application\Authoring\CreateTest;
use Src\Assessment\Application\Authoring\PublishTest;
use Src\Assessment\Application\Authoring\QuestionDraft;
use Src\Assessment\Application\Authoring\SectionDraft;
use Src\Assessment\Application\Authoring\UploadQuestionImage;
use Src\Assessment\Domain\QuestionOption;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\SectionFormat;
use Src\Shared\Application\Actor;

/**
 * A figural skill test of the system library with **pictures as the options** (`docs/design/phase-8-question-images.md`): in each item five figures are drawn, A to E, and the candidate picks the one that differs from
 * the others. The figures are simple shapes drawn here with GD, so the demo ships no picture of a published instrument. The first item is a sample, so the practice and the catalogue preview show pictures too.
 */
final readonly class DemoFiguralTest
{
    private const string INSTRUCTION = 'Pilihlah salah satu gambar yang berbeda dari gambar lain.';

    private const int SIZE = 120;

    /** The items: the kind of figure, the position (1 to 5) of the one that differs, and whether the item is the sample. */
    private const array ITEMS = [
        ['dots', 3, true],
        ['triangle', 5, false],
        ['slash', 2, false],
    ];

    public function __construct(
        private CreateTest $createTest,
        private AddSection $addSection,
        private AddQuestion $addQuestion,
        private UploadQuestionImage $upload,
        private PublishTest $publish,
    ) {}

    /** @return int the id of the published test */
    public function write(Actor $admin): int
    {
        $testId = (int) $this->createTest->execute(
            $admin, 'Penalaran Figural (contoh fiktif)', 'Tes contoh dengan opsi berupa gambar: pilih gambar yang berbeda dari yang lain. Gambarnya sederhana dan dibuat khusus untuk demo.',
            'Setiap soal menampilkan lima gambar bertanda A sampai E. Pilih satu gambar yang berbeda.', ['Penalaran figural'],
        )->id;
        $section = $this->addSection->execute($admin, $testId, new SectionDraft('Gambar yang berbeda', null, SectionFormat::Questions, 300, true, true, true, null, []));

        foreach (self::ITEMS as $number => [$kind, $odd, $sample]) {
            $options = [];
            $optionImages = [];

            foreach (['A', 'B', 'C', 'D', 'E'] as $index => $letter) {
                $options[] = new QuestionOption($letter, $index + 1 === $odd);
                $optionImages[] = (int) $this->upload->execute($admin, $testId, $this->figure($kind, $index + 1 === $odd), "Gambar pilihan {$letter}")->id;
            }

            $this->addQuestion->execute($admin, (int) $section->id, new QuestionDraft(
                QuestionType::SingleChoice, 'Soal '.($number + 1).'. '.self::INSTRUCTION, 1, null, $sample, $options, null, null, null, [], $optionImages,
            ));
        }

        $this->publish->execute($admin, $testId);

        return $testId;
    }

    /** A figure of the kind: the usual one, or the one that differs. */
    private function figure(string $kind, bool $odd): string
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $ink = (int) imagecolorallocate($image, 30, 30, 30);

        imagefill($image, 0, 0, $white);
        imagesetthickness($image, 3);
        imagerectangle($image, 2, 2, self::SIZE - 3, self::SIZE - 3, (int) imagecolorallocate($image, 180, 180, 180));

        match ($kind) {
            'dots' => $this->dots($image, $ink, $odd ? 5 : 3),
            'triangle' => $this->triangle($image, $ink, $odd),
            default => $this->slash($image, $ink, $odd),
        };

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function dots(GdImage $image, int $ink, int $count): void
    {
        imageellipse($image, 60, 60, 100, 100, $ink);
        $places = [[60, 60], [40, 40], [80, 40], [40, 80], [80, 80]];

        foreach (array_slice($places, 0, $count) as [$x, $y]) {
            imagefilledellipse($image, $x, $y, 10, 10, $ink);
        }
    }

    private function triangle(GdImage $image, int $ink, bool $down): void
    {
        $points = $down ? [20, 30, 100, 30, 60, 95] : [60, 25, 20, 95, 100, 95];

        imagepolygon($image, $points, $ink);
    }

    private function slash(GdImage $image, int $ink, bool $back): void
    {
        imagerectangle($image, 25, 25, 95, 95, $ink);
        $back ? imageline($image, 25, 25, 95, 95, $ink) : imageline($image, 25, 95, 95, 25, $ink);
    }
}
