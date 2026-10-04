<?php

namespace Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\TranslatedText;

final class TranslatedTextTest extends TestCase
{
    public function test_only_supported_languages_are_kept_and_values_are_trimmed(): void
    {
        $text = TranslatedText::fromArray(['id' => '  Halo ', 'en' => 'Hello', 'fr' => 'Bonjour', 'de' => 5]);

        $this->assertSame(['id' => 'Halo', 'en' => 'Hello'], $text->toArray());
    }

    public function test_missing_or_non_string_translations_become_empty_strings(): void
    {
        $this->assertSame(['id' => '', 'en' => ''], TranslatedText::fromArray([])->toArray());
        $this->assertSame(['id' => '', 'en' => ''], TranslatedText::fromArray(['id' => null, 'en' => ['x']])->toArray());
    }

    public function test_resolving_prefers_the_requested_language_and_falls_back_to_the_other(): void
    {
        $both = TranslatedText::fromArray(['id' => 'Halo', 'en' => 'Hello']);
        $onlyEnglish = TranslatedText::fromArray(['en' => 'Hello']);

        $this->assertSame('Halo', $both->resolve('id'));
        $this->assertSame('Hello', $both->resolve('en'));
        $this->assertSame('Hello', $onlyEnglish->resolve('id'));
        $this->assertSame('', TranslatedText::fromArray([])->resolve('id'));
        $this->assertSame('Halo', $both->resolve('unsupported'));
    }

    public function test_completeness_needs_every_language(): void
    {
        $this->assertTrue(TranslatedText::fromArray(['id' => 'a', 'en' => 'b'])->isCompleteInAllLanguages());
        $this->assertFalse(TranslatedText::fromArray(['id' => 'a'])->isCompleteInAllLanguages());
        $this->assertFalse(TranslatedText::fromArray(['id' => 'a', 'en' => '   '])->isCompleteInAllLanguages());
    }
}
