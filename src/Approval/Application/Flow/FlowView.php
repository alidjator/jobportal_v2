<?php

namespace Src\Approval\Application\Flow;

use Src\Approval\Domain\Flow;
use Src\Shared\Application\Approval\ApprovalSubjectType;

/** One subject type with the flow that applies to the owner: `source` is `company` (its own flow), `group` (the group's default) or `none`; `flow` is null when `none`. */
final readonly class FlowView
{
    public function __construct(public ApprovalSubjectType $type, public ?Flow $flow, public string $source) {}
}
