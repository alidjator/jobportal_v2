<?php

namespace Src\Shared\Domain\Exceptions;

/** The request conflicts with the current state of a resource (HTTP 409), for example moving an application out of a final stage. */
final class StateConflict extends DomainException
{
    public const string DEFAULT_CODE = 'state-conflict';
}
