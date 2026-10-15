<?php

namespace Src\Organization\Domain;

enum LegalEntityStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
