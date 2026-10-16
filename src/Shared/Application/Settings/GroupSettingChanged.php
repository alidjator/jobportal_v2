<?php

namespace Src\Shared\Application\Settings;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/** The group's value of a group-editable setting changed. Other contexts react to the settings they own. */
final readonly class GroupSettingChanged implements DomainEvent
{
    /**
     * @param  int|bool|string|list<string>  $before  the value that applied to the group before
     * @param  int|bool|string|list<string>  $after  the value that applies to the group now
     */
    public function __construct(
        public int $groupId,
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
