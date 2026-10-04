<?php

namespace Src\Platform\Application\Settings;

use DateTimeImmutable;

/** A value an admin set for a setting, with when and by whom. */
final readonly class StoredSetting
{
    public function __construct(
        public mixed $value,
        public DateTimeImmutable $updatedAt,
        public ?int $updatedByAdminId,
    ) {}
}
