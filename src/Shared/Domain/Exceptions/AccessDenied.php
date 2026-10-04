<?php

namespace Src\Shared\Domain\Exceptions;

/** The actor is not allowed to perform the action (HTTP 403). */
final class AccessDenied extends DomainException
{
    public const string DEFAULT_CODE = 'forbidden';
}
