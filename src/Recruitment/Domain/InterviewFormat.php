<?php

namespace Src\Recruitment\Domain;

enum InterviewFormat: string
{
    case Online = 'online';
    case Onsite = 'onsite';
    // Held inside the platform on the self-hosted video server; needs neither a place nor a link.
    case Video = 'video';
}
