<?php

namespace Src\JobSeeker\Domain;

/** A language a job seeker speaks and how well. */
final readonly class LanguageEntry
{
    public function __construct(public int $languageId, public LanguageProficiency $proficiency) {}
}
