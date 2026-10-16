<?php

namespace Src\Approval\Domain;

/** The group or the company a flow belongs to. */
final readonly class FlowOwner
{
    public function __construct(public OwnerType $type, public int $id) {}

    public static function group(int $groupId): self
    {
        return new self(OwnerType::Group, $groupId);
    }

    public static function company(int $companyId): self
    {
        return new self(OwnerType::Company, $companyId);
    }
}
