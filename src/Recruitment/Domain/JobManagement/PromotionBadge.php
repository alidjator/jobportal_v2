<?php

namespace Src\Recruitment\Domain\JobManagement;

enum PromotionBadge: string
{
    case Featured = 'featured';
    case Highlight = 'highlight';
}
