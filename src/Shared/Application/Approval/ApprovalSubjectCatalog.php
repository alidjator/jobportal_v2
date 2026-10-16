<?php

namespace Src\Shared\Application\Approval;

use InvalidArgumentException;

/** Every subject type that goes through approval. Each context registers its own from its service provider (like the permission catalog), so the Approval context knows nothing of its subjects. */
final class ApprovalSubjectCatalog
{
    /** @var array<string, ApprovalSubjectType> */
    private array $types = [];

    public function add(ApprovalSubjectType ...$types): void
    {
        foreach ($types as $type) {
            if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $type->code)) {
                throw new InvalidArgumentException("Subject type {$type->code} must look like area.name in lower snake case.");
            }

            if (isset($this->types[$type->code])) {
                throw new InvalidArgumentException("Subject type {$type->code} is already registered.");
            }

            $this->types[$type->code] = $type;
        }
    }

    public function find(string $code): ?ApprovalSubjectType
    {
        return $this->types[$code] ?? null;
    }

    /** @return list<ApprovalSubjectType> sorted by code */
    public function all(): array
    {
        $types = $this->types;
        ksort($types);

        return array_values($types);
    }
}
