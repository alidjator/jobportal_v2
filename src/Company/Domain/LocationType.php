<?php

namespace Src\Company\Domain;

/** What kind of place a work location is. */
enum LocationType: string
{
    case HeadOffice = 'head_office';
    case Branch = 'branch';
    case Site = 'site';
}
