<?php

namespace Tests\Unit\Shared\Application;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Application\Settings\SettingDefinition;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class SettingDefinitionTest extends TestCase
{
    private function integer(): SettingDefinition
    {
        return SettingDefinition::integer('probe.limit', 5, 1, 50, 'A limit');
    }

    public function test_an_integer_inside_the_range_is_accepted(): void
    {
        $this->assertSame(1, $this->integer()->normalize(1));
        $this->assertSame(50, $this->integer()->normalize(50));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidIntegers(): array
    {
        return ['below the range' => [0], 'above the range' => [51], 'a string' => ['5'], 'a float' => [5.5], 'a boolean' => [true], 'null' => [null], 'a list' => [[5]]];
    }

    #[DataProvider('invalidIntegers')]
    public function test_an_integer_outside_the_range_or_of_another_type_is_refused(mixed $value): void
    {
        try {
            $this->integer()->normalize($value);
            $this->fail('The value must be refused.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('invalid-setting-value', $e->errorCode());
            $this->assertStringContainsString('probe.limit', $e->getMessage());
        }
    }

    public function test_a_boolean_must_be_a_real_boolean(): void
    {
        $definition = SettingDefinition::boolean('probe.enabled', true, 'A switch');

        $this->assertFalse($definition->normalize(false));
        $this->expectException(BusinessRuleViolation::class);
        $definition->normalize('true');
    }

    public function test_text_is_trimmed_and_limited_in_length(): void
    {
        $definition = SettingDefinition::text('probe.label', 'x', 5, 'A label');

        $this->assertSame('abc', $definition->normalize('  abc '));
        $this->expectException(BusinessRuleViolation::class);
        $definition->normalize('abcdef');
    }

    public function test_choices_are_checked_against_the_allowed_values_and_deduplicated(): void
    {
        $definition = SettingDefinition::choices('probe.templates', ['classic'], ['classic', 'modern', 'compact'], 'Templates', minItems: 1);

        $this->assertSame(['modern', 'classic'], $definition->normalize(['modern', 'classic', 'modern']));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidChoices(): array
    {
        return ['unknown value' => [['classic', 'fancy']], 'too few' => [[]], 'not a list' => [['a' => 'classic']], 'not text' => [[1]], 'not an array' => ['classic']];
    }

    #[DataProvider('invalidChoices')]
    public function test_invalid_choices_are_refused(mixed $value): void
    {
        $definition = SettingDefinition::choices('probe.templates', ['classic'], ['classic', 'modern'], 'Templates', minItems: 1);

        $this->expectException(BusinessRuleViolation::class);

        $definition->normalize($value);
    }

    public function test_the_catalog_registers_and_finds_definitions_sorted_by_key(): void
    {
        $catalog = new SettingsCatalog;
        $catalog->add(SettingDefinition::integer('screening.max_questions', 5, 1, 20, 'x'), SettingDefinition::boolean('alerts.enabled', true, 'y'));

        $this->assertSame(['alerts.enabled', 'screening.max_questions'], array_map(fn ($d) => $d->key, $catalog->all()));
        $this->assertSame(5, $catalog->find('screening.max_questions')?->default);
        $this->assertNull($catalog->find('nothing.here'));
    }

    /** @return array<string, array{string}> */
    public static function badKeys(): array
    {
        return ['no area' => ['limit'], 'upper case' => ['Probe.limit'], 'dash' => ['probe.max-limit'], 'trailing dot' => ['probe.'], 'starts with digit' => ['1probe.limit']];
    }

    #[DataProvider('badKeys')]
    public function test_a_key_must_look_like_area_dot_name(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SettingsCatalog)->add(SettingDefinition::integer($key, 5, 1, 10, 'x'));
    }

    public function test_a_duplicate_key_is_refused(): void
    {
        $catalog = new SettingsCatalog;
        $catalog->add($this->integer());

        $this->expectException(InvalidArgumentException::class);

        $catalog->add($this->integer());
    }

    public function test_a_default_the_setting_itself_would_refuse_is_caught_at_registration(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        (new SettingsCatalog)->add(SettingDefinition::integer('probe.limit', 99, 1, 10, 'x'));
    }

    public function test_a_default_that_is_not_canonical_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SettingsCatalog)->add(SettingDefinition::text('probe.label', '  padded  ', 20, 'x'));
    }
}
