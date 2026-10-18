<?php

namespace Src\Platform\Application\EmailSuppression;

enum EmailSuppressionReason: string
{
    case HardBounce = 'hard_bounce';
    case Complaint = 'complaint';
    case Manual = 'manual';
}
