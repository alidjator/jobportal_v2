<?php

namespace Src\Review\Domain;

/** Why a review was reported. */
enum ReportReason: string
{
    case Spam = 'spam';
    case Abusive = 'abusive';
    case FalseClaim = 'false-claim';
    case PersonalData = 'personal-data';
    case Other = 'other';
}
