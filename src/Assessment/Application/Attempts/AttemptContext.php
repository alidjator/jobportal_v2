<?php

namespace Src\Assessment\Application\Attempts;

use DateTimeImmutable;
use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationStatus;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\Test;

/** An attempt with what the use cases need around it, read once: the invitation, the test and its sections. The attempt is already rolled to `$now`. */
final readonly class AttemptContext
{
    /**
     * @param  list<Section>  $sections
     * @param  list<int>  $durations
     */
    public function __construct(
        public Invitation $invitation,
        public Attempt $attempt,
        public Test $test,
        public array $sections,
        public array $durations,
        public DateTimeImmutable $now,
    ) {}

    /** The candidate can still work: not submitted and the invitation was not cancelled. */
    public function isOpen(): bool
    {
        return ! $this->attempt->isSubmitted() && $this->invitation->status === InvitationStatus::Started && ! $this->attempt->isOutOfTime($this->durations);
    }

    public function section(): Section
    {
        return $this->sections[$this->attempt->sectionPosition - 1];
    }

    public function withAttempt(Attempt $attempt): self
    {
        return new self($this->invitation, $attempt, $this->test, $this->sections, $this->durations, $this->now);
    }
}
