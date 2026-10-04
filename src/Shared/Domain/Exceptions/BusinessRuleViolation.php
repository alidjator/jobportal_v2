<?php

namespace Src\Shared\Domain\Exceptions;

/** A syntactically valid request refused by a business rule (HTTP 422), for example exceeding a daily limit. */
final class BusinessRuleViolation extends DomainException
{
    public const string DEFAULT_CODE = 'business-rule-violation';
}
