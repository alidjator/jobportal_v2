<?php

namespace Src\Shared\Application\Calendar;

/** The sources of the recruitment calendar. Each context registers its own from its service provider. */
final class CalendarSources
{
    /** @var list<CalendarSource> */
    private array $sources = [];

    public function add(CalendarSource ...$sources): void
    {
        foreach ($sources as $source) {
            $this->sources[] = $source;
        }
    }

    /** @return list<CalendarSource> */
    public function all(): array
    {
        return $this->sources;
    }
}
