<?php

namespace Src\Assessment\Application\Catalog;

/**
 * A test a member may send, as the "send a test" dialog lists it. `kind` is `skill` or `psychological`; `source` says where it comes from: `company` (its own published
 * skill test), `library` (the system library) or `battery` (granted to the company).
 */
final readonly class SendableTest
{
    public const string COMPANY = 'company';

    public const string LIBRARY = 'library';

    public const string BATTERY = 'battery';

    public function __construct(
        public int $id,
        public string $title,
        public string $kind,
        public string $source,
        public ?string $description,
        public int $sectionCount,
        public int $totalSeconds,
    ) {}
}
