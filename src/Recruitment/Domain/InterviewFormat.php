<?php

namespace Src\Recruitment\Domain;

enum InterviewFormat: string
{
    case Online = 'online';
    case Onsite = 'onsite';
}
