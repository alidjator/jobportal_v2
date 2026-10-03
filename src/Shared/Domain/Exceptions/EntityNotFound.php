<?php

namespace Src\Shared\Domain\Exceptions;

final class EntityNotFound extends DomainException
{
    public static function of(string $entity, string|int|null $id = null): self
    {
        return new self($id === null ? "{$entity} not found" : "{$entity} not found: {$id}");
    }
}
