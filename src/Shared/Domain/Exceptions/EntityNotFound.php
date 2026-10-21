<?php

namespace Src\Shared\Domain\Exceptions;

/** The requested entity does not exist or is not visible to the actor (HTTP 404). */
final class EntityNotFound extends DomainException
{
    public const string DEFAULT_CODE = 'not-found';

    /** `$entity` is part of the template (`Job not found: :id`), so each entity has its own translation; the id is a parameter. */
    public static function of(string $entity, string|int|null $id = null): self
    {
        if ($id === null) {
            return new self("{$entity} not found");
        }

        return new self("{$entity} not found: :id", parameters: ['id' => $id]);
    }
}
