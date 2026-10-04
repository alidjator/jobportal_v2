<?php

namespace Src\ReferenceData\Application\Masters;

/** A column of a master table beyond the common ones (name, slug, order, active flag), shown to the public and set by admins. */
final readonly class ExtraField
{
    /** @param  list<string>  $choices the allowed values of a `Choice` field */
    private function __construct(
        public string $name,
        public FieldType $type,
        public bool $required,
        public bool $unique,
        public int $max,
        public array $choices,
    ) {}

    public static function text(string $name, int $max, bool $required = true, bool $unique = false): self
    {
        return new self($name, FieldType::Text, $required, $unique, $max, []);
    }

    public static function integer(string $name, bool $required = true, int $max = 4_294_967_295): self
    {
        return new self($name, FieldType::Integer, $required, false, $max, []);
    }

    /** @param  list<string>  $choices */
    public static function choice(string $name, array $choices, bool $required = true): self
    {
        return new self($name, FieldType::Choice, $required, false, 0, $choices);
    }
}
