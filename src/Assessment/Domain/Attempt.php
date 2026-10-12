<?php

namespace Src\Assessment\Domain;

use DateTimeImmutable;
use LogicException;

/**
 * The one attempt of an invitation. The candidate works through the sections in order and the server keeps the time: a section closes at its deadline
 * (when it opened plus its duration) whether or not the candidate is there, and the next one opens at that moment. Finishing a section early opens the next
 * one at once, so the end of the whole test only ever moves earlier. When the last section closes the attempt is out of time and is submitted by the server.
 * `sectionPosition` counts from 1; a value above the number of sections means every section has closed.
 */
final readonly class Attempt
{
    public function __construct(
        public ?int $id,
        public int $invitationId,
        public DateTimeImmutable $consentedAt,
        public DateTimeImmutable $startedAt,
        public int $sectionPosition,
        public DateTimeImmutable $sectionOpenedAt,
        public int $questionCursor,
        public DateTimeImmutable $endsAt,
        public ?DateTimeImmutable $submittedAt,
        public bool $autoSubmitted,
        public int $seed,
        public ?string $lastIp,
        public ?DateTimeImmutable $videoConsentedAt = null,
    ) {}

    /** @param  list<int>  $durations  the seconds of each section, in order */
    public static function begin(int $invitationId, DateTimeImmutable $now, array $durations, int $seed, ?string $ip, bool $videoConsent = false): self
    {
        return new self(null, $invitationId, $now, $now, 1, $now, 0, $now->modify('+'.array_sum($durations).' seconds'), null, false, $seed, $ip, $videoConsent ? $now : null);
    }

    public function isSubmitted(): bool
    {
        return $this->submittedAt !== null;
    }

    /** @param  list<int>  $durations */
    public function isOutOfTime(array $durations): bool
    {
        return $this->sectionPosition > count($durations);
    }

    /** @param  list<int>  $durations */
    public function sectionDeadline(array $durations): DateTimeImmutable
    {
        $seconds = $durations[$this->sectionPosition - 1] ?? throw new LogicException('Every section has closed.');

        return $this->sectionOpenedAt->modify("+{$seconds} seconds");
    }

    /**
     * Closes every section whose deadline has passed and opens the one that follows at that deadline.
     *
     * @param  list<int>  $durations
     */
    public function rolledTo(DateTimeImmutable $now, array $durations): self
    {
        $position = $this->sectionPosition;
        $opened = $this->sectionOpenedAt;

        while ($position <= count($durations) && $opened->modify("+{$durations[$position - 1]} seconds") <= $now) {
            $opened = $opened->modify("+{$durations[$position - 1]} seconds");
            $position++;
        }

        return $position === $this->sectionPosition ? $this : $this->copy(sectionPosition: $position, sectionOpenedAt: $opened, questionCursor: 0);
    }

    /**
     * The candidate finishes the open section and opens the next one now.
     *
     * @param  list<int>  $durations
     */
    public function nextSection(DateTimeImmutable $now, array $durations): self
    {
        $position = $this->sectionPosition + 1;

        return $this->copy(
            sectionPosition: $position,
            sectionOpenedAt: $now,
            questionCursor: 0,
            endsAt: $now->modify('+'.array_sum(array_slice($durations, $position - 1)).' seconds'),
        );
    }

    public function movedTo(int $cursor): self
    {
        return $this->copy(questionCursor: $cursor);
    }

    public function seenFrom(?string $ip): self
    {
        return $this->copy(lastIp: $ip);
    }

    /** A submission by the server is stamped with the moment the time ran out, not with when the server noticed. */
    public function submit(DateTimeImmutable $now, bool $auto): self
    {
        $at = $auto && $this->endsAt < $now ? $this->endsAt : $now;

        return $this->copy(submittedAt: $at, autoSubmitted: $auto);
    }

    private function copy(
        ?int $sectionPosition = null,
        ?DateTimeImmutable $sectionOpenedAt = null,
        ?int $questionCursor = null,
        ?DateTimeImmutable $endsAt = null,
        ?DateTimeImmutable $submittedAt = null,
        ?bool $autoSubmitted = null,
        ?string $lastIp = null,
    ): self {
        return new self(
            $this->id, $this->invitationId, $this->consentedAt, $this->startedAt,
            $sectionPosition ?? $this->sectionPosition,
            $sectionOpenedAt ?? $this->sectionOpenedAt,
            $questionCursor ?? $this->questionCursor,
            $endsAt ?? $this->endsAt,
            $submittedAt ?? $this->submittedAt,
            $autoSubmitted ?? $this->autoSubmitted,
            $this->seed,
            $lastIp ?? $this->lastIp,
            $this->videoConsentedAt,
        );
    }
}
