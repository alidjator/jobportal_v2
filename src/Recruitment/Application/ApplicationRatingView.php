<?php

namespace Src\Recruitment\Application;

/** The ratings of an application: the average (one decimal, null when nobody rated), how many rated, the viewer's own stars and every member's. */
final readonly class ApplicationRatingView
{
    /** @param  list<MemberRating>  $members */
    public function __construct(public ?float $average, public int $count, public ?int $mine, public array $members) {}
}
