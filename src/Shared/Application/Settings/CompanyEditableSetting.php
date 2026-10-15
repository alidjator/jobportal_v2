<?php

namespace Src\Shared\Application\Settings;

/** One setting on the company settings page: its value for the company, the platform default and the range the company admin may choose from. */
final readonly class CompanyEditableSetting
{
    /**
     * @param  int|bool|string|list<string>  $value  what applies to the company
     * @param  int|bool|string|list<string>  $default  the platform default
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
