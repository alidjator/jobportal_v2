<?php

namespace Src\Assessment\Domain;

/** Who wrote a message about a report: a member of the company that asked, or a psychologist of the institution that signed. */
enum ReportMessageAuthor: string
{
    case Company = 'company';
    case Psychologist = 'psychologist';
}
