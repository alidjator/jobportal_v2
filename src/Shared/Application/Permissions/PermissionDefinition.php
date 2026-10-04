<?php

namespace Src\Shared\Application\Permissions;

/** One admin permission: a dotted name (`legal_documents.manage`) and the group it is listed under (`content`). */
final readonly class PermissionDefinition
{
    public function __construct(
        public string $name,
        public string $group,
    ) {}
}
