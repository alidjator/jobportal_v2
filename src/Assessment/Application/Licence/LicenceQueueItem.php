<?php

namespace Src\Assessment\Application\Licence;

use Src\Assessment\Domain\LicenceQueueRow;
use Src\Shared\Application\Accounts\MemberAccountSummary;

/** A queue row with the name and email of the psychologist's account. */
final readonly class LicenceQueueItem
{
    public function __construct(
        public LicenceQueueRow $row,
        public ?MemberAccountSummary $account,
    ) {}
}
