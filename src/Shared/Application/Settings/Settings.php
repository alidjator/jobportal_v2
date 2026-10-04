<?php

namespace Src\Shared\Application\Settings;

/**
 * Reads the current value of a setting: the company's own value when it has one (and the setting allows it), otherwise the global
 * value an admin set, otherwise the default from the catalog. Asking for an unregistered key or with the wrong type is a programming
 * error and throws.
 */
interface Settings
{
    public function integer(string $key, ?int $companyId = null): int;

    public function boolean(string $key, ?int $companyId = null): bool;

    public function text(string $key, ?int $companyId = null): string;

    /** @return list<string> */
    public function choices(string $key, ?int $companyId = null): array;
}
