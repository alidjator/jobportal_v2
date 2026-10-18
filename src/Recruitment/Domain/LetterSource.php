<?php

namespace Src\Recruitment\Domain;

/** Who made the letter of an offer: the platform from the terms, or the company (its own PDF). */
enum LetterSource: string
{
    case Generated = 'generated';
    case Uploaded = 'uploaded';
}
