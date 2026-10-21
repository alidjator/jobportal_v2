<?php

namespace Src\Shared\Domain\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base for every domain and application error. The HTTP layer maps each subclass to a status code and a problem
 * `type`; the domain itself knows nothing about HTTP.
 *
 * The machine-readable error code is kebab-case (for example `already-applied`). A use case passes a specific code
 * when it raises the error; otherwise the generic default of the subclass applies. An error may also carry extra,
 * client-readable data (`extensions`), which the HTTP layer adds to the problem details body.
 *
 * The message is English and doubles as the translation key (`docs/design/phase-8-api-translations.md`). A message that holds values is a template
 * with `:name` placeholders and the values as `parameters` (`'The score must be a whole number from 0 to :points.'`, `['points' => 5]`); the
 * placeholders of the messages in `extensions.errors` take the same parameters, unless a message is an {@see ErrorMessage} with its own. An
 * `ErrorMessage` may also be the message itself. `getMessage()` is the English text with the values filled in; the HTTP layer translates `template()` instead.
 */
abstract class DomainException extends RuntimeException
{
    public const string DEFAULT_CODE = 'domain-error';

    private readonly string $template;

    /** @var array<string, string|int|float> */
    private readonly array $parameters;

    /**
     * @param  array<string, mixed>  $extensions
     * @param  array<string, string|int|float>  $parameters
     */
    public function __construct(
        string|ErrorMessage $message = '',
        private readonly ?string $errorCode = null,
        ?Throwable $previous = null,
        private readonly array $extensions = [],
        array $parameters = [],
    ) {
        if ($message instanceof ErrorMessage) {
            $parameters = [...$message->parameters, ...$parameters];
            $message = $message->template;
        }

        $this->template = $message;
        $this->parameters = $parameters;
        parent::__construct(self::fill($message, $parameters), 0, $previous);
    }

    /**
     * The text with each `:name` placeholder replaced by its value; longer names first, so `:max` does not eat into `:max_size`.
     *
     * @param  array<string, string|int|float>  $parameters
     */
    public static function fill(string $text, array $parameters): string
    {
        if ($parameters === []) {
            return $text;
        }

        uksort($parameters, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return strtr($text, array_combine(array_map(static fn (string $name): string => ':'.$name, array_keys($parameters)), array_map('strval', $parameters)));
    }

    public function errorCode(): string
    {
        return $this->errorCode ?? static::DEFAULT_CODE;
    }

    /** @return array<string, mixed> */
    public function extensions(): array
    {
        return $this->extensions;
    }

    /** The English message before the values are filled in: the translation key. */
    public function template(): string
    {
        return $this->template;
    }

    /** @return array<string, string|int|float> */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
