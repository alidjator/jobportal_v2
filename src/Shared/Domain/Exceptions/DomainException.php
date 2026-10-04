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
 */
abstract class DomainException extends RuntimeException
{
    public const string DEFAULT_CODE = 'domain-error';

    /** @param  array<string, mixed>  $extensions */
    public function __construct(
        string $message = '',
        private readonly ?string $errorCode = null,
        ?Throwable $previous = null,
        private readonly array $extensions = [],
    ) {
        parent::__construct($message, 0, $previous);
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
}
