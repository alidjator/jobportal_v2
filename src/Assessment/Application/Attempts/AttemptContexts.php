<?php

namespace Src\Assessment\Application\Attempts;

use DateTimeImmutable;
use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Reads what surrounds an attempt: its test and sections. */
final readonly class AttemptContexts
{
    public function __construct(
        private TestRepository $tests,
        private SectionRepository $sections,
    ) {}

    public function load(Invitation $invitation, Attempt $attempt, DateTimeImmutable $now): AttemptContext
    {
        $sections = $this->sections->ofTest($invitation->testId);

        return new AttemptContext(
            $invitation,
            $attempt,
            $this->tests->find($invitation->testId) ?? throw EntityNotFound::of('Test', $invitation->testId),
            $sections,
            array_map(static fn (Section $section): int => $section->durationSeconds, $sections),
            $now,
        );
    }
}
