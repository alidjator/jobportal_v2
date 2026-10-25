<?php

namespace Src\Shared\Application\Portal;

/** The owner of a portal brand or draft. The platform has no id. */
final readonly class PortalOwner
{
    private function __construct(public PortalOwnerType $type, public int $id) {}

    public static function platform(): self
    {
        return new self(PortalOwnerType::Platform, 0);
    }

    public static function group(int $groupId): self
    {
        return new self(PortalOwnerType::Group, $groupId);
    }

    public static function company(int $companyId): self
    {
        return new self(PortalOwnerType::Company, $companyId);
    }
}
