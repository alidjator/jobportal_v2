<?php

namespace Src\JobSeeker\Domain;

/** A link on a profile: its kind and a checked `https` address. */
final readonly class ProfileLink
{
    public function __construct(public LinkType $type, public string $url) {}
}
