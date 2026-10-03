<?php

namespace Src\Identity\Domain;

enum ResetCodeStatus
{
    case Valid;
    case Invalid;
    case Expired;
}
