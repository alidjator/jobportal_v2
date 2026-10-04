<?php

namespace Src\Platform\Application\Settings;

/**
 * Where setting values live. Reads are cached by the implementation and every write makes the next read see it. The store does
 * not validate: use cases check values against the catalog before writing.
 */
interface SettingsStore
{
    public function values(): StoredValues;

    public function setGlobal(string $key, mixed $value, ?int $adminId): void;

    /** Removes the stored global value, so the setting is back at its default. Returns whether there was one. */
    public function resetGlobal(string $key): bool;

    public function setCompanyOverride(string $key, int $companyId, mixed $value, ?int $adminId): void;

    public function removeCompanyOverride(string $key, int $companyId): bool;
}
