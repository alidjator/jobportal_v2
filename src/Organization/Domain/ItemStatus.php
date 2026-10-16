<?php

namespace Src\Organization\Domain;

/** The status of a structure item: an item that something refers to is deactivated, never deleted. */
enum ItemStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
