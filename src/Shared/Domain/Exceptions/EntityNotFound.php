<?php

namespace Src\Shared\Domain\Exceptions;

/** The requested entity does not exist or is not visible to the actor (HTTP 404). */
final class EntityNotFound extends DomainException
{
    public const string DEFAULT_CODE = 'not-found';

    public static function of(string $entity, string|int|null $id = null): self
    {
        return new self($id === null ? "{$entity} not found" : "{$entity} not found: {$id}");
    }
}
