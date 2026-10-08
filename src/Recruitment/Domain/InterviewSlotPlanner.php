<?php

namespace Src\Recruitment\Domain;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Plans the start times of several interviews that follow one another (a bulk move to `interview`): from a start, each slot lasts `duration` minutes and is
 * followed by a gap. Slots stay inside the daily window on working days (Monday to Friday, in the given zone); one that would not fit moves to the start
 * of the next working day. Busy ranges (the interviewer's other interviews) are skipped: a slot that would overlap one starts when it ends.
 */
final class InterviewSlotPlanner
{
    /**
     * @param  string  $dayStart  the first minute of the working day, `H:i`
     * @param  string  $dayEnd  the end of the working day, `H:i`; a slot must end by then
     * @param  list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>  $busy  start and end of the ranges no slot may overlap
     * @return list<array{0: DateTimeImmutable, 1: DateTimeImmutable}> start and end of each slot, in UTC
     *
     * @throws InvalidArgumentException when the window is empty or a slot does not fit in it
     */
    public function plan(DateTimeImmutable $start, int $count, int $durationMinutes, int $gapMinutes, string $dayStart, string $dayEnd, DateTimeZone $zone, array $busy = []): array
    {
        $length = $this->minutes($dayEnd) - $this->minutes($dayStart);

        if ($durationMinutes < 1 || $gapMinutes < 0 || $durationMinutes > $length) {
            throw new InvalidArgumentException('A slot must fit in the working day.');
        }

        $slots = [];
        $cursor = $start->setTimezone($zone);
        $duration = new DateInterval('PT'.$durationMinutes.'M');
        $gap = new DateInterval('PT'.$gapMinutes.'M');

        for ($index = 0; $index < $count; $index++) {
            $cursor = $this->free($cursor, $dayStart, $dayEnd, $durationMinutes, $duration, $busy);
            $end = $cursor->add($duration);
            $slots[] = [$cursor->setTimezone(new DateTimeZone('UTC')), $end->setTimezone(new DateTimeZone('UTC'))];
            $cursor = $end->add($gap);
        }

        return $slots;
    }

    /**
     * @param  list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>  $busy
     */
    private function free(DateTimeImmutable $at, string $dayStart, string $dayEnd, int $durationMinutes, DateInterval $duration, array $busy): DateTimeImmutable
    {
        while (true) {
            $at = $this->insideWindow($at, $dayStart, $dayEnd, $durationMinutes);
            $end = $at->add($duration);

            foreach ($busy as [$busyStart, $busyEnd]) {
                if ($busyStart < $end && $busyEnd > $at) {
                    $at = $busyEnd->setTimezone($at->getTimezone());

                    continue 2;
                }
            }

            return $at;
        }
    }

    private function insideWindow(DateTimeImmutable $at, string $dayStart, string $dayEnd, int $durationMinutes): DateTimeImmutable
    {
        while (true) {
            $opens = $this->at($at, $dayStart);

            if ($this->isWeekend($at)) {
                $at = $this->at($at->modify('+1 day'), $dayStart);

                continue;
            }

            if ($at < $opens) {
                $at = $opens;
            }

            if ($at->add(new DateInterval('PT'.$durationMinutes.'M')) > $this->at($at, $dayEnd)) {
                $at = $this->at($at->modify('+1 day'), $dayStart);

                continue;
            }

            return $at;
        }
    }

    private function at(DateTimeImmutable $day, string $time): DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $day->setTime($hour, $minute);
    }

    private function isWeekend(DateTimeImmutable $day): bool
    {
        return (int) $day->format('N') >= 6;
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }
}
