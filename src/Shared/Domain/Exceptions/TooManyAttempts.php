<?php

namespace Src\Shared\Domain\Exceptions;

use Throwable;

/** An attempt limit was exceeded (HTTP 429); the client may retry after `retryAfterSeconds`. */
final class TooManyAttempts extends DomainException
{
    public const string DEFAULT_CODE = 'too-many-requests';

    public function __construct(string $message = '', public readonly int $retryAfterSeconds = 60, ?string $errorCode = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $errorCode, $previous);
    }
}
