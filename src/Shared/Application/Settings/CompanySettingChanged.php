<?php

namespace Src\Shared\Application\Settings;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** The value of an overridable setting that applies to a company changed (its own value was set, changed or removed). Other contexts react to the settings they own. */
final readonly class CompanySettingChanged implements DomainEvent
{
    /**
     * @param  int|bool|string|list<string>  $before  the value that applied before
     * @param  int|bool|string|list<string>  $after  the value that applies now
     */
    public function __construct(
        public int $companyId,
        public string $key,
        public int|bool|string|array $before,
        public int|bool|string|array $after,
        private DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
