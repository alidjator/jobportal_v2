<?php

namespace Src\Recruitment\Domain;

/** The letter PDF of an offer: where its file is kept, who made it and the SHA-256 of the exact file that was sent. */
final readonly class OfferLetter
{
    public function __construct(public string $path, public LetterSource $source, public string $sha256) {}
}
