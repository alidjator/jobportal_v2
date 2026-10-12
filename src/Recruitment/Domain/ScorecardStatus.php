<?php

namespace Src\Recruitment\Domain;

/** A scorecard is a draft until its member sends it; a sent scorecard can still be edited (with a revision) until the application is final. */
enum ScorecardStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
