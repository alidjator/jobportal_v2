<?php

namespace Src\JobSeeker\Application\Candidates;

/** The picture of a candidate: its bytes and image type. */
final readonly class CandidateAvatar
{
    public function __construct(public string $contents, public string $mimeType) {}
}
