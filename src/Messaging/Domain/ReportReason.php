<?php

namespace Src\Messaging\Domain;

enum ReportReason: string
{
    case ScamOrFee = 'scam_or_fee';
    case Harassment = 'harassment';
    case Spam = 'spam';
    case Inappropriate = 'inappropriate';
    case Other = 'other';
}
