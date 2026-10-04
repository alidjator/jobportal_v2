<?php

namespace Src\Platform\Application\Settings;

use DateTimeImmutable;
use Src\Shared\Application\Settings\SettingDefinition;

/** A setting as an admin sees it: its definition and the global value in force (the default until an admin sets one). Company overrides are not part of this view. */
final readonly class SettingView
{
    /** @param  int|bool|string|list<string>  $value */
    public function __construct(
        public SettingDefinition $definition,
        public int|bool|string|array $value,
        public bool $isDefault,
        public ?DateTimeImmutable $updatedAt,
        public ?int $updatedByAdminId,
    ) {}
}
