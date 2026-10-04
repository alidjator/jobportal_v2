<?php

namespace Src\Shared\Domain\Exceptions;

/** A required external service could not be reached or is switched off (HTTP 503). The client may retry later. */
final class ExternalServiceUnavailable extends DomainException
{
    public const string DEFAULT_CODE = 'service-unavailable';
}
