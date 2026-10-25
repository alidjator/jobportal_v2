<?php

namespace Src\Platform\Application\Portal;

/** A company the portal names but cannot show: why (`missing`, `suspended` or `hidden`) and, when it still exists, its name. */
final readonly class PortalSkippedCompany
{
    public const string MISSING = 'missing';

    public const string SUSPENDED = 'suspended';

    public const string HIDDEN = 'hidden';

    public function __construct(public int $id, public ?string $name, public string $reason) {}
}
