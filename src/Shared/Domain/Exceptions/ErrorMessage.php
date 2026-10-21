<?php

namespace Src\Shared\Domain\Exceptions;

use JsonSerializable;
use Stringable;

/**
 * One message with its own values, for a list of messages that do not share them (two sections, each with its title, in one `errors` member) or a helper
 * that takes the message of its caller. The template is the English text and the translation key; as a string or in JSON it is the English text with the
 * values filled in, and the HTTP layer translates the template instead. An error raised with one takes its template and values.
 */
final readonly class ErrorMessage implements JsonSerializable, Stringable
{
    /** @param  array<string, string|int|float>  $parameters */
    public function __construct(
        public string $template,
        public array $parameters = [],
    ) {}

    public function __toString(): string
    {
        return DomainException::fill($this->template, $this->parameters);
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }
}
