<?php

namespace Src\Approval\Application\Instance;

use Src\Approval\Domain\ApprovalInstance;

/**
 * A running or ended approval as an account sees it: the names of the people in it (account id => name), whether the account may decide now and whether it may name a replacement
 * for a vacant approver.
 */
final readonly class ApprovalView
{
    /** @param  array<int, string>  $names */
    public function __construct(public ApprovalInstance $instance, public array $names, public bool $canDecide, public bool $canReplace) {}
}
