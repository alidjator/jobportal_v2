<?php

namespace Src\Company\Application\NameChange;

use Src\Company\Domain\NameChangeRequest;
use Src\Shared\Application\Company\CompanySummary;

/** A name change request as an admin sees it: the request and the company it belongs to (null only if the company row is gone, which a foreign key prevents). */
final readonly class AdminNameChange
{
    public function __construct(
        public NameChangeRequest $request,
        public ?CompanySummary $company,
    ) {}
}
