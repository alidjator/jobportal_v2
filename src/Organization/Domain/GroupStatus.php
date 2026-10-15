<?php

namespace Src\Organization\Domain;

enum GroupStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
