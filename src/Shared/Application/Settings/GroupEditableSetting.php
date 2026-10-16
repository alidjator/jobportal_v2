<?php

namespace Src\Shared\Application\Settings;

/** One setting on the group settings page: its value for the group, the value that applies without a group value (the platform's) and the range the group admin may choose from. */
final readonly class GroupEditableSetting
{
    /**
     * @param  int|bool|string|list<string>  $value  what applies to the group's tenants that have no value of their own
     * @param  int|bool|string|list<string>  $default  what applies without a group value
     * @param  list<string>  $allowedValues  for a `choices` setting
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $description,
        public int|bool|string|array $value,
        public int|bool|string|array $default,
        public ?int $min,
        public ?int $max,
        public array $allowedValues,
    ) {}
}
