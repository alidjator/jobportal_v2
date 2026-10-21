<?php

namespace Src\Shared\Domain\Exceptions;

use Throwable;

/** An attempt limit was exceeded (HTTP 429); the client may retry after `retryAfterSeconds`. */
final class TooManyAttempts extends DomainException
{
    public const string DEFAULT_CODE = 'too-many-requests';

    /** @param  array<string, string|int|float>  $parameters */
    public function __construct(
        string|ErrorMessage $message = '',
        public readonly int $retryAfterSeconds = 60,
        ?string $errorCode = null,
        ?Throwable $previous = null,
        array $parameters = [],
    ) {
        parent::__construct($message, $errorCode, $previous, parameters: $parameters);
    }
}
