<?php

namespace Src\Platform\Application\Settings;

use Src\Shared\Application\Settings\SettingDefinition;

/**
 * An overridable setting as an admin sees it for one company: the global value in force, the company's own value if it has one, and the
 * value that applies to the company.
 */
final readonly class CompanySettingView
{
    /**
     * @param  int|bool|string|list<string>  $globalValue
     * @param  int|bool|string|list<string>|null  $override  what is stored for the company; null when it has none
     * @param  int|bool|string|list<string>  $effectiveValue
     */
    public function __construct(
        public SettingDefinition $definition,
        public int|bool|string|array $globalValue,
        public int|bool|string|array|null $override,
        public bool $hasOverride,
        public int|bool|string|array $effectiveValue,
    ) {}
}
