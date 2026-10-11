<?php

namespace Src\Assessment\Domain;

enum TestKind: string
{
    case Skill = 'skill';
    case Psychological = 'psychological';
}
