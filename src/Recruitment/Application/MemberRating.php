<?php

namespace Src\Recruitment\Application;

/** The stars one member gave, with the member's name (null when the account no longer has one). */
final readonly class MemberRating
{
    public function __construct(public int $userId, public ?string $name, public int $stars) {}
}
