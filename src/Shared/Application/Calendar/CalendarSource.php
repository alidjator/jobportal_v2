<?php

namespace Src\Shared\Application\Calendar;

/**
 * A context's part of the recruitment calendar. The context registers its sources in `CalendarSources` from its service provider, so the Dashboard context that serves the
 * endpoint knows no other context. A source reads on indexed columns inside the window and returns at most `$scope->limit` events, the earliest first.
 */
interface CalendarSource
{
    /** @return list<string> the event types the source can return (see {@see CalendarEvent::TYPES}) */
    public function types(): array;

    /** @return list<CalendarEvent> only events of the types the scope asks for, only what the member may see */
    public function events(CalendarScope $scope): array;
}
