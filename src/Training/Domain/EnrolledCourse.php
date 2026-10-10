<?php

namespace Src\Training\Domain;

use DateTimeImmutable;

/** A course a job seeker is enrolled in, with their progress (0 to 100, null when the platform does not track it) and the last time they opened it. */
final readonly class EnrolledCourse
{
    public function __construct(
        public Course $course,
        public ?float $progress,
        public ?DateTimeImmutable $lastAccessAt,
    ) {}
}
