<?php

namespace Src\Assessment\Domain;

/** The two licences a psychologist signs under: the registration certificate (STR) and the practice licence (SILP). */
enum LicenceKind: string
{
    case Str = 'str';
    case Silp = 'silp';
}
