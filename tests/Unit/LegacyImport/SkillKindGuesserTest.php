<?php

namespace Tests\Unit\LegacyImport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\LegacyImport\Application\SkillKindGuesser;

final class SkillKindGuesserTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function names(): array
    {
        return [
            'english soft skill' => ['Team Leadership', 'soft'],
            'indonesian soft skill' => ['Kerja Sama Tim', 'soft'],
            'stem of a word' => ['Problem Solving', 'soft'],
            'letter case does not matter' => ['PUBLIC SPEAKING', 'soft'],
            'a tool is hard' => ['Laravel', 'hard'],
            'a technique is hard' => ['Welding', 'hard'],
        ];
    }

    #[DataProvider('names')]
    public function test_a_skill_is_soft_only_when_its_name_contains_a_soft_word(string $name, string $kind): void
    {
        $this->assertSame($kind, SkillKindGuesser::guess($name));
    }
}
