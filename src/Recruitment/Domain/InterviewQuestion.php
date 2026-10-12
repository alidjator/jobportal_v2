<?php

namespace Src\Recruitment\Domain;

/** A question an interview keeps, with the id scorecard answers refer to. */
final readonly class InterviewQuestion
{
    public function __construct(public int $id, public KitQuestion $question) {}
}
