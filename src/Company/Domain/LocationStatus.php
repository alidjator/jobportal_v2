<?php

namespace Src\Company\Domain;

/** An inactive location leaves the pickers; what already points to it still shows it. Nothing that is referenced is deleted. */
enum LocationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
