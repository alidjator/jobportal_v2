<?php

namespace Src\Content\Domain;

enum AnnouncementLevel: string
{
    case Info = 'info';
    case Important = 'important';
}
