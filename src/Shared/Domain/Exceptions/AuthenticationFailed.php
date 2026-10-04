<?php

namespace Src\Shared\Domain\Exceptions;

/** The supplied credentials are wrong (HTTP 401). The message must not reveal which part was wrong. */
final class AuthenticationFailed extends DomainException
{
    public const string DEFAULT_CODE = 'invalid-credentials';
}
