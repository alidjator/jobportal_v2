<?php

namespace Src\Platform\Application\Settings;

use InvalidArgumentException;
use LogicException;
use Src\Shared\Application\Organization\GroupDirectory;
use Src\Shared\Application\Settings\SettingDefinition;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Settings\SettingType;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The reader other contexts use: the company's own value (when the setting allows it), then the value of the company's group (when the setting allows it and the
 * company is an internal tenant), then the global value, then the default. A stored value that the setting's definition no longer accepts (the catalog changed after the
 * value was saved) is ignored, so the next source, and finally the default, applies instead of breaking the feature.
 */
final readonly class StoredSettings implements Settings
{
    public function __construct(
        private SettingsCatalog $catalog,
        private SettingsStore $store,
        private GroupDirectory $groups,
    ) {}

    public function integer(string $key, ?int $companyId = null): int
    {
        $value = $this->resolve($key, SettingType::Integer, $companyId);

        return is_int($value) ? $value : throw new LogicException("Setting {$key} is not an integer.");
    }

    public function boolean(string $key, ?int $companyId = null): bool
    {
        $value = $this->resolve($key, SettingType::Boolean, $companyId);

        return is_bool($value) ? $value : throw new LogicException("Setting {$key} is not a boolean.");
    }

    public function text(string $key, ?int $companyId = null): string
    {
        $value = $this->resolve($key, SettingType::Text, $companyId);

        return is_string($value) ? $value : throw new LogicException("Setting {$key} is not text.");
    }

    public function choices(string $key, ?int $companyId = null): array
    {
        $value = $this->resolve($key, SettingType::Choices, $companyId);

        return is_array($value) ? array_values(array_map('strval', $value)) : throw new LogicException("Setting {$key} is not a list.");
    }

    private function resolve(string $key, SettingType $expected, ?int $companyId): mixed
    {
        $definition = $this->catalog->find($key) ?? throw new InvalidArgumentException("Setting {$key} is not registered.");

        if ($definition->type !== $expected) {
            throw new LogicException("Setting {$key} is of type {$definition->type->value}, not {$expected->value}.");
        }

        $values = $this->store->values();
        $candidates = [];

        if ($companyId !== null && $definition->overridable && array_key_exists($companyId, $values->companyOverrides[$key] ?? [])) {
            $candidates[] = $values->companyOverrides[$key][$companyId];
        }

        if ($companyId !== null && $definition->groupEditable) {
            $groupId = $this->groups->groupOfCompany($companyId);

            if ($groupId !== null && array_key_exists($groupId, $values->groupValues[$key] ?? [])) {
                $candidates[] = $values->groupValues[$key][$groupId];
            }
        }

        if (isset($values->globals[$key])) {
            $candidates[] = $values->globals[$key]->value;
        }

        foreach ($candidates as $candidate) {
            $accepted = $this->accepted($definition, $candidate);

            if ($accepted !== null) {
                return $accepted;
            }
        }

        return $definition->default;
    }

    /** @return int|bool|string|list<string>|null the value in canonical form, or null when the definition refuses it */
    private function accepted(SettingDefinition $definition, mixed $value): int|bool|string|array|null
    {
        try {
            return $definition->normalize($value);
        } catch (BusinessRuleViolation) {
            return null;
        }
    }
}
