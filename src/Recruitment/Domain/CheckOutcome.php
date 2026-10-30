<?php

namespace Src\Recruitment\Domain;

/** The result of a check. For a medical check it reads fit, fit with notes, unfit. */
enum CheckOutcome: string
{
    case Pass = 'pass';
    case PassWithNotes = 'pass_with_notes';
    case Fail = 'fail';
}
