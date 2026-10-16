<?php

namespace Src\Shared\Application\Settings;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * One admin-configurable setting: its key (`area.name`), type, default, constraints and whether a company may have its own
 * value, and whether a group may (`groupEditable`: the value then applies to every tenant of the group that has no value of its own). Contexts register their definitions in the `SettingsCatalog`; the database stores only values that differ from the default. A
 * `companyEditable` setting is one the company admin changes on the company settings page (it implies `overridable`).
 * Settings are not for secrets: values are shown to admins and written to the audit log.
 */
final readonly class SettingDefinition
{
    /**
     * @param  list<string>  $allowedValues  for `Choices`
     * @param  int|bool|string|list<string>  $default
     */
    private function __construct(
        public string $key,
        public SettingType $type,
        public int|bool|string|array $default,
        public string $description,
        public bool $overridable,
        public ?int $min,
        public ?int $max,
        public array $allowedValues,
        public int $minItems,
        public bool $companyEditable = false,
        public bool $groupEditable = false,
    ) {}

    public static function integer(string $key, int $default, int $min, int $max, string $description, bool $overridable = false, bool $companyEditable = false, bool $groupEditable = false): self
    {
        return new self($key, SettingType::Integer, $default, $description, $overridable || $companyEditable, $min, $max, [], 0, $companyEditable, $groupEditable);
    }

    public static function boolean(string $key, bool $default, string $description, bool $overridable = false, bool $companyEditable = false, bool $groupEditable = false): self
    {
        return new self($key, SettingType::Boolean, $default, $description, $overridable || $companyEditable, null, null, [], 0, $companyEditable, $groupEditable);
    }

    public static function text(string $key, string $default, int $maxLength, string $description, bool $overridable = false, bool $companyEditable = false, bool $groupEditable = false): self
    {
        return new self($key, SettingType::Text, $default, $description, $overridable || $companyEditable, null, $maxLength, [], 0, $companyEditable, $groupEditable);
    }

    /**
     * @param  list<string>  $default
     * @param  list<string>  $allowedValues
     */
    public static function choices(string $key, array $default, array $allowedValues, string $description, int $minItems = 0, bool $overridable = false, bool $companyEditable = false, bool $groupEditable = false): self
    {
        return new self($key, SettingType::Choices, $default, $description, $overridable || $companyEditable, null, null, $allowedValues, $minItems, $companyEditable, $groupEditable);
    }

    /**
     * Checks a submitted value and returns it in its canonical form (an integer, a boolean, a string or a list without duplicates).
     *
     * @return int|bool|string|list<string>
     *
     * @throws BusinessRuleViolation `invalid-setting-value`
     */
    public function normalize(mixed $value): int|bool|string|array
    {
        return match ($this->type) {
            SettingType::Integer => $this->normalizeInteger($value),
            SettingType::Boolean => is_bool($value) ? $value : throw $this->invalid('must be true or false'),
            SettingType::Text => $this->normalizeText($value),
            SettingType::Choices => $this->normalizeChoices($value),
        };
    }

    private function normalizeInteger(mixed $value): int
    {
        if (! is_int($value)) {
            throw $this->invalid('must be a whole number');
        }

        if ($value < (int) $this->min || $value > (int) $this->max) {
            throw $this->invalid("must be between {$this->min} and {$this->max}");
        }

        return $value;
    }

    private function normalizeText(mixed $value): string
    {
        if (! is_string($value)) {
            throw $this->invalid('must be text');
        }

        $value = trim($value);

        if (mb_strlen($value) > (int) $this->max) {
            throw $this->invalid("must be at most {$this->max} characters");
        }

        return $value;
    }

    /** @return list<string> */
    private function normalizeChoices(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || array_filter($value, fn (mixed $item): bool => ! is_string($item)) !== []) {
            throw $this->invalid('must be a list of text values');
        }

        $unknown = array_values(array_diff($value, $this->allowedValues));
        if ($unknown !== []) {
            throw $this->invalid('contains values that are not allowed: '.implode(', ', $unknown).'. Allowed: '.implode(', ', $this->allowedValues));
        }

        $unique = array_values(array_unique($value));

        if (count($unique) < $this->minItems) {
            throw $this->invalid("needs at least {$this->minItems} value(s)");
        }

        return $unique;
    }

    private function invalid(string $problem): BusinessRuleViolation
    {
        return new BusinessRuleViolation("The value of {$this->key} {$problem}.", 'invalid-setting-value');
    }
}
