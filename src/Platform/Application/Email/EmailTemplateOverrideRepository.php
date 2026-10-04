<?php

namespace Src\Platform\Application\Email;

interface EmailTemplateOverrideRepository
{
    public function find(string $templateKey, string $locale): ?EmailTemplateOverride;

    /** @return array<string, EmailTemplateOverride> the overrides of the template, keyed by language */
    public function forTemplate(string $templateKey): array;

    /** Creates or replaces the override of the template in the language. */
    public function save(string $templateKey, string $locale, string $subject, string $body, ?int $adminId): EmailTemplateOverride;

    /** Removes the override, so the default applies again. Returns whether there was one. */
    public function delete(string $templateKey, string $locale): bool;
}
