<?php

namespace Src\Company\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A set of permissions a company admin saved under a name, to give the same access to several members. It is a starting point: applying it copies the permissions to
 * the member, so a later change of the preset changes nobody. Immutable: a change returns a new instance.
 */
final readonly class PermissionPreset
{
    public const int NAME_MAX_LENGTH = 60;

    public const int MAX_PER_COMPANY = 20;

    /** @param  list<string>  $permissions  catalog names, in catalog order */
    public function __construct(
        public int $companyId,
        public string $name,
        public array $permissions,
        public int $createdByUserId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    /**
     * @param  list<string>  $permissions  names already checked against the catalog
     *
     * @throws BusinessRuleViolation `permissions-required`, `permission-requires-view` or `permission-requires-review`
     */
    public static function create(int $companyId, string $name, array $permissions, int $createdByUserId, DateTimeImmutable $now): self
    {
        return new self($companyId, trim($name), MemberPermissionRules::validated($permissions), $createdByUserId, $now, $now);
    }

    /**
     * @param  list<string>  $permissions  names already checked against the catalog
     *
     * @throws BusinessRuleViolation `permissions-required`, `permission-requires-view` or `permission-requires-review`
     */
    public function changed(string $name, array $permissions, DateTimeImmutable $now): self
    {
        return new self($this->companyId, trim($name), MemberPermissionRules::validated($permissions), $this->createdByUserId, $this->createdAt, $now, $this->id);
    }
}
