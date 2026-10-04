<?php

namespace Src\Platform\Application\Settings;

use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Application\Settings\SettingType;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Builds the admin view of settings: the definition plus the global value in force. */
final readonly class SettingViews
{
    public function __construct(
        private SettingsCatalog $catalog,
        private SettingsStore $store,
        private StoredSettings $reader,
    ) {}

    /** @return list<SettingView> sorted by key */
    public function all(): array
    {
        return array_map(fn ($definition): SettingView => $this->one($definition->key), $this->catalog->all());
    }

    /** @throws EntityNotFound when no setting has this key */
    public function one(string $key): SettingView
    {
        $definition = $this->catalog->find($key) ?? throw EntityNotFound::of('Setting', $key);
        $stored = $this->store->values()->globals[$key] ?? null;

        $value = match ($definition->type) {
            SettingType::Integer => $this->reader->integer($key),
            SettingType::Boolean => $this->reader->boolean($key),
            SettingType::Text => $this->reader->text($key),
            SettingType::Choices => $this->reader->choices($key),
        };

        return new SettingView($definition, $value, $stored === null, $stored?->updatedAt, $stored?->updatedByAdminId);
    }
}
