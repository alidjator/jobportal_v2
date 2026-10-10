<?php

namespace Src\Review\Domain;

/** Who reported a review: a job seeker or the company. */
enum ReporterKind: string
{
    case User = 'user';
    case Company = 'company';
}
