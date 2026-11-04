<?php

namespace Tests\Unit\Assessment\Application;

use PHPUnit\Framework\TestCase;
use Src\Assessment\Application\Library\Figural\FiguralLayout;
use Src\Assessment\Application\Library\Figural\FiguralReasoningContent;
use Src\Assessment\Infrastructure\Figures\GdFigureRenderer;

/** `docs/design/phase-8-figural-reasoning-test.md`: the shape of the item bank (four subtests of ten, five different options, a key in range, stems that fit their layout) and the pictures it draws. */
final class FiguralReasoningContentTest extends TestCase
{
    /** The frames of the stem each layout needs. */
    private const array STEM_FRAMES = ['options_only' => 0, 'series' => 4, 'analogy' => 3, 'matrix' => 8];

    public function test_there_are_four_subtests_of_ten_scored_items_and_one_sample(): void
    {
        $sections = FiguralReasoningContent::sections();

        $this->assertSame([FiguralLayout::OptionsOnly, FiguralLayout::Series, FiguralLayout::Analogy, FiguralLayout::Matrix], array_map(static fn ($section) => $section->layout, $sections));

        $samples = 0;

        foreach ($sections as $section) {
            $scored = array_filter($section->items, static fn ($item): bool => ! $item->isSample);
            $samples += count($section->items) - count($scored);
            $this->assertCount(10, $scored, $section->title);
            $this->assertGreaterThanOrEqual(60, $section->seconds);
        }

        $this->assertSame(1, $samples);
    }

    public function test_every_item_has_five_different_options_a_key_in_range_and_the_stem_of_its_layout(): void
    {
        $keys = [];

        foreach (FiguralReasoningContent::sections() as $section) {
            foreach ($section->items as $number => $item) {
                $label = "{$section->title} #{$number}";
                $this->assertCount(5, $item->options, $label);
                $options = array_map('serialize', $item->options);

                if ($section->layout === FiguralLayout::OptionsOnly) {
                    // The four figures that follow the rule may repeat; the odd one must differ from all of them.
                    $this->assertNotContains($options[$item->key], array_diff_key($options, [$item->key => true]), "{$label}: the odd figure repeats another");
                } else {
                    $this->assertCount(5, array_unique($options), "{$label}: two options are the same figure");
                }
                $this->assertContains($item->key, [0, 1, 2, 3, 4], $label);
                $this->assertCount(self::STEM_FRAMES[$section->layout->value], $item->stem, $label);
                $this->assertNotSame('', $item->rule, $label);
                $keys[$section->title][] = $item->key;
            }

            $this->assertNotNull($section->layout === FiguralLayout::OptionsOnly ? '' : $section->stemAlt, $section->title);
        }

        foreach ($keys as $title => $positions) {
            $this->assertGreaterThanOrEqual(4, count(array_unique($positions)), "{$title}: the keys should be spread over the letters");
        }
    }

    public function test_the_renderer_draws_png_pictures_within_the_upload_limit(): void
    {
        $renderer = new GdFigureRenderer;

        foreach (FiguralReasoningContent::sections() as $section) {
            $item = $section->items[array_key_last($section->items)];
            $pictures = array_map(static fn (array $frame): string => $renderer->frame($frame), $item->options);

            if ($section->layout !== FiguralLayout::OptionsOnly) {
                $pictures[] = $renderer->stem($section->layout, $item->stem);
            }

            foreach ($pictures as $picture) {
                $size = getimagesizefromstring($picture);
                $this->assertNotFalse($size);
                $this->assertSame(IMAGETYPE_PNG, $size[2]);
                $this->assertLessThanOrEqual(1600, max($size[0], $size[1]));
            }
        }
    }
}
