<?php

namespace Src\Recruitment\Domain;

/** Whether the monthly base salary of an offer is before (gross) or after (net) tax. */
enum SalaryBasis: string
{
    case Gross = 'gross';
    case Net = 'net';
}
