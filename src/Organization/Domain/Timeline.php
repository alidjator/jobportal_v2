<?php

namespace Src\Organization\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The versions of one unit or position. They never overlap and never leave a gap: a new version closes the one it cuts into the day before its start and ends the day
 * before the next version that already starts later (a restructuring entered ahead of its date stays in place), or stays open.
 */
final class Timeline
{
    /**
     * @template T of Versioned
     *
     * @param  list<T>  $versions
     * @return list<T> by start date
     */
    public static function sorted(array $versions): array
    {
        usort($versions, static fn (Versioned $a, Versioned $b): int => $a->startsOn() <=> $b->startsOn());

        return $versions;
    }

    /**
     * The version valid on the date, or null before the first one starts.
     *
     * @template T of Versioned
     *
     * @param  list<T>  $versions
     * @return T|null
     */
    public static function at(array $versions, DateTimeImmutable $date): ?Versioned
    {
        foreach ($versions as $version) {
            if ($version->startsOn() <= $date && ($version->endsOn() === null || $version->endsOn() >= $date)) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Whether the timeline has started by the date. A timeline has no gap and its last version stays open, so it then covers every later day: this is "valid over the whole
     * period" of a version that starts on the date.
     *
     * @param  list<Versioned>  $versions
     */
    public static function startedBy(array $versions, DateTimeImmutable $date): bool
    {
        return self::sorted($versions) !== [] && self::sorted($versions)[0]->startsOn() <= $date;
    }

    /**
     * The timeline with the new version in it.
     *
     * @template T of Versioned
     *
     * @param  list<T>  $versions
     * @param  T  $new
     * @return list<T>
     *
     * @throws BusinessRuleViolation `before-first-version`
     */
    public static function with(array $versions, Versioned $new): array
    {
        $versions = self::sorted($versions);
        $from = $new->startsOn();

        if ($versions !== [] && $from < $versions[0]->startsOn()) {
            throw new BusinessRuleViolation('The change is dated before the first version exists.', 'before-first-version');
        }

        $dayBefore = $from->modify('-1 day');
        $result = [];
        $nextStart = null;

        foreach ($versions as $version) {
            if ($version->startsOn() == $from) {
                continue;
            }

            if ($version->startsOn() > $from) {
                $nextStart ??= $version->startsOn();
                $result[] = $version;

                continue;
            }

            $result[] = $version->endsOn() === null || $version->endsOn() >= $from ? $version->withPeriod($version->startsOn(), $dayBefore) : $version;
        }

        $result[] = $new->withPeriod($from, $nextStart?->modify('-1 day'));

        return self::sorted($result);
    }

    /**
     * Whether the new version, placed in the timelines, closes a loop through the stable id `$rootId` at any day of its period. The days that matter are its start and every
     * day another version starts inside the period (the parent of a unit can change meanwhile).
     *
     * @param  array<int, list<Versioned>>  $timelines  every timeline of the structure, the changed one already updated, by stable id
     * @param  callable(Versioned): ?int  $parentOf  the stable id a version points at
     */
    public static function closesLoop(array $timelines, int $rootId, Versioned $version, callable $parentOf): bool
    {
        $days = [$version->startsOn()];

        foreach ($timelines as $versions) {
            foreach ($versions as $other) {
                $start = $other->startsOn();

                if ($start > $version->startsOn() && ($version->endsOn() === null || $start <= $version->endsOn())) {
                    $days[] = $start;
                }
            }
        }

        foreach ($days as $day) {
            $node = $parentOf($version);

            for ($steps = 0; $node !== null && $steps <= count($timelines); $steps++) {
                if ($node === $rootId) {
                    return true;
                }

                $current = self::at($timelines[$node] ?? [], $day);
                $node = $current === null ? null : $parentOf($current);
            }
        }

        return false;
    }
}
