<?php

namespace Src\Review\Domain;

/** Why an admin rejected or took down a review or a reply. */
enum ModerationReason: string
{
    case Spam = 'spam';
    case Abusive = 'abusive';
    case NotAboutCompany = 'not-about-company';
    case PersonalData = 'personal-data';
    case FalseClaim = 'false-claim';
    case Other = 'other';
}
