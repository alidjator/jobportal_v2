<?php

namespace Src\Approval\Domain;

/** One approver of a step: the account id of a member, a group permission name, or a relative role name, as text. */
final readonly class FlowApprover
{
    public function __construct(public ApproverKind $kind, public string $value) {}
}
