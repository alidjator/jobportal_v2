<?php

namespace Src\Platform\Application\Settings;

use Src\Shared\Application\Settings\SettingDefinition;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Settings\SettingType;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Builds the admin view of the settings a company may override: global value, the company's own value, and the one that applies. */
final readonly class CompanySettingViews
{
    public function __construct(
        private SettingsCatalog $catalog,
        private SettingsStore $store,
        private StoredSettings $reader,
    ) {}

    /** @return list<CompanySettingView> only the overridable settings, sorted by key */
    public function all(int $companyId): array
    {
        $views = [];

        foreach ($this->catalog->all() as $definition) {
            if ($definition->overridable) {
                $views[] = $this->build($definition, $companyId);
            }
        }

        return $views;
    }

    /** @throws EntityNotFound when no overridable setting has this key */
    public function one(int $companyId, string $key): CompanySettingView
    {
        $definition = $this->catalog->find($key);

        if ($definition === null || ! $definition->overridable) {
            throw EntityNotFound::of('Overridable setting', $key);
        }

        return $this->build($definition, $companyId);
    }

    private function build(SettingDefinition $definition, int $companyId): CompanySettingView
    {
        $overrides = $this->store->values()->companyOverrides[$definition->key] ?? [];
        $hasOverride = array_key_exists($companyId, $overrides);

        return new CompanySettingView(
            $definition,
            $this->read($definition, null),
            $hasOverride ? $overrides[$companyId] : null,
            $hasOverride,
            $this->read($definition, $companyId),
        );
    }

    /** @return int|bool|string|list<string> */
    private function read(SettingDefinition $definition, ?int $companyId): int|bool|string|array
    {
        return match ($definition->type) {
            SettingType::Integer => $this->reader->integer($definition->key, $companyId),
            SettingType::Boolean => $this->reader->boolean($definition->key, $companyId),
            SettingType::Text => $this->reader->text($definition->key, $companyId),
            SettingType::Choices => $this->reader->choices($definition->key, $companyId),
        };
    }
}
