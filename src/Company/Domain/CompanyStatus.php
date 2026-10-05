<?php

namespace Src\Company\Domain;

/** A suspended company disappears from public view and its members cannot use company features; nothing is deleted. */
enum CompanyStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
