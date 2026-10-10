<?php

namespace Src\Shared\Infrastructure\Documentation;

use Dedoc\Scramble\Support\Generator\InfoObject;

/**
 * The `info` object of the document with the OpenAPI `contact` object, which Scramble's own `InfoObject` does not write.
 */
final class InfoWithContact extends InfoObject
{
    /** @param array{name?: string, url?: string, email?: string} $contact */
    public function __construct(string $title, string $version, string $description, private readonly array $contact)
    {
        parent::__construct($title, $version);

        $this->description = $description;
    }

    /** @param array<array-key, mixed> $contact */
    public static function from(InfoObject $info, array $contact): self
    {
        return new self($info->title, $info->version, $info->description, array_filter(array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $contact)));
    }

    public function toArray(): array
    {
        $info = parent::toArray();

        if ($this->contact !== []) {
            $info['contact'] = $this->contact;
        }

        return $info;
    }
}
